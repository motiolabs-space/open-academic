import Alpine from 'alpinejs';

/**
 * Live weighted-grade preview on the grade-entry sheet.
 *
 * This is a convenience for the lecturer typing, nothing more: the grade that
 * actually gets stored is recomputed by PenilaianService when the form is
 * submitted. Duplicating the formula here is acceptable precisely because this
 * copy never decides anything.
 *
 * @param {Record<number, number>} bobot komponen id => weight percentage
 * @param {Array<{letter: string, min: number}>} skala highest to lowest
 */
Alpine.data('lembarNilai', (bobot, skala) => ({
    baris: {},

    mulai(id, nilai) {
        this.baris[id] = { ...nilai };
    },

    ubah(id, komponenId, nilai) {
        this.baris[id][komponenId] = nilai === '' ? null : parseFloat(nilai);
    },

    akhir(id) {
        const isian = this.baris[id] ?? {};
        let total = 0;

        for (const [komponenId, persen] of Object.entries(bobot)) {
            total += (parseFloat(isian[komponenId]) || 0) * (persen / 100);
        }

        return Math.round(total * 100) / 100;
    },

    /** Indonesian decimal comma, matching every other number in the app. */
    akhirTeks(id) {
        return this.akhir(id).toFixed(2).replace('.', ',');
    },

    huruf(id) {
        const nilai = this.akhir(id);
        const cocok = skala.find((baris) => nilai >= baris.min);

        return cocok ? cocok.letter : 'E';
    },

    hurufKelas(id) {
        const huruf = this.huruf(id);

        if (huruf === 'A') return 'bg-navy text-gold';
        if (huruf === 'E') return 'bg-danger-bg text-danger';

        return 'bg-line/60 text-ink';
    },
}));
