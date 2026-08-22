/**
 * Concurrent HTTP load against a real multi-process web server.
 *
 * The one technical debt the Pest suite cannot close. `artisan serve` is
 * single-threaded, so it answers requests one at a time no matter how many
 * arrive — a load test against it measures the client. XAMPP's Apache is
 * mpm_winnt with 150 worker threads, which is a genuine queue.
 *
 * What this measures is deliberately narrow: KRS opening, the one minute in a
 * semester when the entire student body arrives at once. It logs in a set of
 * distinct students, then replays the catalogue request at rising concurrency
 * and reports latency percentiles, error rates, and which layer gives first.
 *
 * Three failure modes are separated on purpose, because the remedies differ:
 *
 *   antrean   Apache runs out of worker threads; requests wait for a thread.
 *   koneksi   MySQL refuses new connections (max_connections).
 *   sesi      Session store contention. Laravel here uses the database driver,
 *             so every request reads and writes one row of `sessions`.
 *
 * Not production hardware, and it does not claim to be. What it answers is
 * where the knee is on THIS machine and which layer bends first — a shape that
 * carries over even when the numbers do not.
 *
 * Usage (from app-core):
 *   node scripts/beban-http.cjs
 *   node scripts/beban-http.cjs --base=http://localhost/open-academic --max=60
 *
 * Berekstensi .cjs karena package.json menyatakan "type": "module"; berkas ini
 * memakai require() dan bukan bagian dari bundel Vite.
 */

const http = require('http');
const { URL } = require('url');

const argumen = Object.fromEntries(
    process.argv.slice(2)
        .filter((a) => a.startsWith('--'))
        .map((a) => a.slice(2).split('=')),
);

const BASE = argumen.base || 'http://localhost/open-academic';
const JALUR = argumen.path || '/mahasiswa/krs';
const MAKS = Number(argumen.max || 40);
const PER_TINGKAT = Number(argumen.n || 60);
/*
 * Daftar akun yang dipakai.
 *
 * Wajib berisi mahasiswa yang BERSTATUS AKTIF pada semester berjalan. Yang
 * sudah lulus ditolak KrsService dengan benar, dan memasukkannya ke dalam
 * pengukuran akan mencampur penolakan aturan akademik dengan kegagalan beban —
 * dua hal yang justru sedang dipisahkan di sini.
 */
const EMAIL = (argumen.emails || '')
    .split(',')
    .map((e) => e.trim())
    .filter(Boolean);

const TINGKAT = (argumen.levels || '1,5,10,20,40')
    .split(',')
    .map(Number)
    .filter((n) => n > 0 && n <= MAKS);

/* ------------------------------------------------------------------ *
 | HTTP dasar
 * ------------------------------------------------------------------ */

function minta(metode, url, { cookie = '', body = null, ikutiRedirect = true } = {}) {
    return new Promise((resolve) => {
        const u = new URL(url);
        const data = body ? new URLSearchParams(body).toString() : null;

        const req = http.request({
            hostname: u.hostname,
            port: u.port || 80,
            path: u.pathname + u.search,
            method: metode,
            headers: {
                ...(cookie ? { Cookie: cookie } : {}),
                ...(data
                    ? {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Content-Length': Buffer.byteLength(data),
                    }
                    : {}),
                Accept: 'text/html',
            },
        }, (res) => {
            const potongan = [];
            res.on('data', (c) => potongan.push(c));
            res.on('end', () => {
                resolve({
                    status: res.statusCode,
                    headers: res.headers,
                    body: Buffer.concat(potongan).toString('utf8'),
                });
            });
        });

        req.on('error', (e) => resolve({ status: 0, headers: {}, body: '', error: e.code || e.message }));
        req.setTimeout(60000, () => { req.destroy(); resolve({ status: 0, headers: {}, body: '', error: 'TIMEOUT' }); });

        if (data) req.write(data);
        req.end();
    });
}

/** Kumpulkan cookie dari Set-Cookie, pertahankan yang lama bila tak diganti. */
function gabungCookie(lama, res) {
    const jar = new Map(
        lama.split('; ').filter(Boolean).map((c) => {
            const i = c.indexOf('=');
            return [c.slice(0, i), c.slice(i + 1)];
        }),
    );

    for (const baris of res.headers['set-cookie'] || []) {
        const [pasangan] = baris.split(';');
        const i = pasangan.indexOf('=');
        jar.set(pasangan.slice(0, i).trim(), pasangan.slice(i + 1));
    }

    return [...jar].map(([k, v]) => `${k}=${v}`).join('; ');
}

/* ------------------------------------------------------------------ *
 | Sesi mahasiswa
 * ------------------------------------------------------------------ */

/**
 * Sesi yang benar-benar login.
 *
 * Distinct per student on purpose. Sharing one session would measure session
 * contention instead of request throughput, and those are separate questions.
 */
async function masuk(email) {
    const halaman = await minta('GET', `${BASE}/masuk`);
    let cookie = gabungCookie('', halaman);

    const token = (halaman.body.match(/name="_token"\s+value="([^"]+)"/) || [])[1];
    if (!token) return { ok: false, sebab: 'token CSRF tidak ditemukan' };

    const kirim = await minta('POST', `${BASE}/masuk`, {
        cookie,
        body: { _token: token, identitas: email, password: 'password' },
    });

    cookie = gabungCookie(cookie, kirim);

    if (kirim.status !== 302) return { ok: false, sebab: `login membalas ${kirim.status}` };

    /*
     * A failed login also answers 302 — straight back to the form.
     *
     * Treating any redirect as success is how a load test ends up measuring
     * the login page instead of the page under test, then reporting the
     * resulting redirects as failures of something else entirely. Confirmed by
     * fetching the target once before the session is used.
     */
    const uji = await minta('GET', `${BASE}${JALUR}`, { cookie });

    if (uji.status !== 200) {
        return { ok: false, sebab: `${JALUR} membalas ${uji.status}` };
    }

    return { ok: true, cookie };
}

/* ------------------------------------------------------------------ *
 | Pengukuran
 * ------------------------------------------------------------------ */

function persentil(angka, p) {
    if (angka.length === 0) return null;
    const urut = [...angka].sort((a, b) => a - b);
    return urut[Math.min(urut.length - 1, Math.floor((p / 100) * urut.length))];
}

/** Satu tingkat konkurensi: `jumlah` permintaan, `serentak` sekaligus. */
async function ukur(sesi, serentak, jumlah) {
    const durasi = [];
    const galat = new Map();
    let indeks = 0;

    const mulai = process.hrtime.bigint();

    async function pekerja(nomor) {
        while (true) {
            const ke = indeks++;
            if (ke >= jumlah) return;

            const s = sesi[(nomor + ke) % sesi.length];
            const t0 = process.hrtime.bigint();
            const res = await minta('GET', `${BASE}${JALUR}`, { cookie: s.cookie });
            const ms = Number(process.hrtime.bigint() - t0) / 1e6;

            if (res.status === 200) {
                durasi.push(ms);
            } else {
                const kunci = res.error || `HTTP ${res.status}`;
                galat.set(kunci, (galat.get(kunci) || 0) + 1);
            }
        }
    }

    await Promise.all(Array.from({ length: serentak }, (_, i) => pekerja(i)));

    const total = Number(process.hrtime.bigint() - mulai) / 1e9;

    return {
        serentak,
        sukses: durasi.length,
        gagal: jumlah - durasi.length,
        galat: [...galat].map(([k, v]) => `${k}×${v}`).join(', '),
        rps: (durasi.length / total).toFixed(1),
        p50: persentil(durasi, 50)?.toFixed(0),
        p95: persentil(durasi, 95)?.toFixed(0),
        maks: durasi.length ? Math.max(...durasi).toFixed(0) : null,
    };
}

/* ------------------------------------------------------------------ *
 | Jalan
 * ------------------------------------------------------------------ */

(async () => {
    console.log(`Sasaran   : ${BASE}${JALUR}`);
    console.log(`Tingkat   : ${TINGKAT.join(', ')} serentak · ${PER_TINGKAT} permintaan per tingkat\n`);

    const perluSesi = Math.max(...TINGKAT);
    const daftar = EMAIL.length ? EMAIL : Array.from({ length: 50 }, (_, i) => `mahasiswa${i + 1}@demo.test`);
    const sesi = [];

    const ditolak = new Map();

    for (const email of daftar) {
        if (sesi.length >= perluSesi) break;

        const hasil = await masuk(email);

        if (hasil.ok) sesi.push(hasil);
        else ditolak.set(hasil.sebab, (ditolak.get(hasil.sebab) || 0) + 1);
    }

    if (sesi.length === 0) {
        console.error("Tidak ada sesi yang terpakai: " + [...ditolak].map(([k, v]) => k + " x" + v).join(", "));
        process.exit(1);
    }

    if (ditolak.size) {
        console.log("Akun dilewati: " + [...ditolak].map(([k, v]) => k + " x" + v).join(", "));
    }

    console.log(`Sesi siap : ${sesi.length}\n`);

    console.log('serentak |  sukses gagal |    rps |   p50    p95   maks | galat');
    console.log('---------+---------------+--------+---------------------+------');

    for (const tingkat of TINGKAT) {
        const h = await ukur(sesi, tingkat, PER_TINGKAT);

        console.log(
            `${String(h.serentak).padStart(8)} | ${String(h.sukses).padStart(7)} ${String(h.gagal).padStart(5)} |`
            + ` ${String(h.rps).padStart(6)} |`
            + ` ${String(h.p50 ?? '-').padStart(5)}  ${String(h.p95 ?? '-').padStart(5)}  ${String(h.maks ?? '-').padStart(5)} |`
            + ` ${h.galat}`,
        );
    }

    console.log('\nSatuan waktu dalam milidetik. Mesin pengembangan, bukan perangkat keras produksi.');
})();
