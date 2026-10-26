<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Sdm\Dosen;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Campus Bridge is spec-first (CLAUDE.md §7), and for a while it was not:
 * routes/api.php served fourteen endpoints while docs/openapi/bridge.yaml
 * described twelve, and nothing noticed. The file did not even parse as
 * YAML — no tool had ever read it.
 *
 * These tests read it. Every bridge.* route must have its path, its method
 * and its exact scopes in the spec; every path in the spec must still be
 * served; and the two endpoints that were missing must answer with the
 * fields their schemas name.
 */
function specBridge(): array
{
    return Yaml::parseFile(base_path('../docs/openapi/bridge.yaml'));
}

/** @return array<string, array{path: string, metode: array<int, string>, scope: array<int, string>}> route name => contract */
function ruteBridge(): array
{
    return collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $r): bool => str_starts_with((string) $r->getName(), 'bridge.'))
        ->mapWithKeys(fn (Route $r): array => [$r->getName() => [
            'path' => Str::after($r->uri(), 'api/bridge/v1'),
            'metode' => array_values(array_diff(array_map('strtolower', $r->methods()), ['head'])),
            'scope' => collect($r->gatherMiddleware())
                ->filter(fn ($m): bool => is_string($m) && str_starts_with($m, 'bridge.scope:'))
                ->map(fn (string $m): string => Str::after($m, 'bridge.scope:'))
                ->sort()
                ->values()
                ->all(),
        ]])
        ->all();
}

it('mendokumentasikan setiap rute bridge.* sebagai path di bridge.yaml', function () {
    $paths = specBridge()['paths'];
    $rute = ruteBridge();

    expect($rute)->not->toBeEmpty();

    $tanpaSpec = collect($rute)
        ->reject(fn (array $r): bool => isset($paths[$r['path']]))
        ->map(fn (array $r, string $nama): string => "{$nama} ({$r['path']})")
        ->values()
        ->all();

    expect($tanpaSpec)->toBe([]);
});

it('mendokumentasikan metode dan scope setiap rute persis seperti middleware-nya', function () {
    $paths = specBridge()['paths'];

    foreach (ruteBridge() as $nama => $r) {
        foreach ($r['metode'] as $metode) {
            $operasi = $paths[$r['path']][$metode] ?? null;

            expect($operasi)->not->toBeNull("{$nama}: {$metode} {$r['path']} tidak ada di spec");

            $scope = collect($operasi['security'] ?? [])->pluck('bearerAuth')->flatten()->sort()->values()->all();

            expect($scope)->toBe($r['scope'], "{$nama}: scope di spec berbeda dari middleware rute");
        }
    }
});

it('tidak menyimpan path yang sudah tidak dilayani', function () {
    $dilayani = collect(ruteBridge())->pluck('path')->all();

    expect(array_values(array_diff(array_keys(specBridge()['paths']), $dilayani)))->toBe([]);
});

describe('dua endpoint yang dulu tertinggal', function () {
    beforeEach(function () {
        $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
        $prodi = Prodi::factory()->create(['kode' => '55201']);

        $this->kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id,
            'mata_kuliah_id' => MataKuliah::factory()->create(['prodi_id' => $prodi->id])->id,
            'prodi_id' => $prodi->id,
            'is_case_method' => true,
        ]);
        $this->kelas->dosen()->attach(Dosen::factory()->create(['prodi_id' => $prodi->id])->id, ['peran' => 'pengampu']);

        $scopes = ['students.read', 'lecturers.read', 'classes.read', 'activities.read', 'graduates.read'];
        $konsumen = BridgeConsumer::create(['nama' => 'Open Campus', 'slug' => 'spec-'.Str::random(6), 'scopes' => $scopes, 'is_active' => true]);
        $this->header = ['Authorization' => 'Bearer '.$konsumen->createToken('uji', $scopes)->plainTextToken, 'Accept' => 'application/json'];

        $this->skema = specBridge()['components']['schemas'];
    });

    it('GET /classes/{uuid} menjawab dengan medan skema CourseClass, dan contohnya pun', function () {
        $data = $this->getJson('/api/bridge/v1/classes/'.$this->kelas->uuid, $this->header)->assertOk()->json('data');
        $properti = array_keys($this->skema['CourseClass']['properties']);
        $contoh = specBridge()['paths']['/classes/{uuid}']['get']['responses']['200']['content']['application/json']['example']['data'];

        expect(array_keys($data))->toEqualCanonicalizing($properti)
            ->and(array_keys($data['pengajar'][0]))->toEqualCanonicalizing(array_keys($this->skema['CourseClass']['properties']['pengajar']['items']['properties']))
            ->and(array_keys($contoh))->toEqualCanonicalizing($properti);
    });

    it('GET /iku-data menjawab dengan medan skema IkuData pada setiap butir, dan contohnya pun', function () {
        $data = $this->getJson('/api/bridge/v1/iku-data', $this->header)->assertOk()->json('data');
        $skema = $this->skema['IkuData']['properties'];
        $contoh = specBridge()['paths']['/iku-data']['get']['responses']['200']['content']['application/json']['example']['data'];

        expect(array_keys($data))->toEqualCanonicalizing(array_keys($skema))
            ->and(array_keys($contoh))->toEqualCanonicalizing(array_keys($skema));

        foreach ($skema as $butir => $definisi) {
            $properti = isset($definisi['$ref'])
                ? $this->skema[Str::afterLast($definisi['$ref'], '/')]['properties']
                : ($definisi['properties'] ?? null);

            if ($properti === null) {
                continue;
            }

            expect(array_keys($data[$butir]))->toEqualCanonicalizing(array_keys($properti), "iku-data.{$butir}")
                ->and(array_keys($contoh[$butir]))->toEqualCanonicalizing(array_keys($properti), "contoh iku-data.{$butir}");
        }
    });

    it('menolak /iku-data bila satu saja dari kelima scope tidak dimiliki', function () {
        $scopes = ['students.read', 'lecturers.read', 'classes.read', 'activities.read'];
        $konsumen = BridgeConsumer::create(['nama' => 'Dasbor', 'slug' => 'spec-'.Str::random(6), 'scopes' => $scopes, 'is_active' => true]);

        $this->getJson('/api/bridge/v1/iku-data', ['Authorization' => 'Bearer '.$konsumen->createToken('uji', $scopes)->plainTextToken, 'Accept' => 'application/json'])
            ->assertForbidden();
    });
});
