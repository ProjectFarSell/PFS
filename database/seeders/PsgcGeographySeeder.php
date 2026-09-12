<?php

namespace Database\Seeders;

use App\Models\PsgcBarangay;
use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use Illuminate\Database\Seeder;
use JsonException;

class PsgcGeographySeeder extends Seeder
{
    private const DATA_DIRECTORY = 'database/data/psgc';

    /**
     * Import the PSGC 2025 Q2 data, sourced from @jobuntux/psgc and kept in
     * the repository so a fresh Laravel/Docker install can seed without Node.
     *
     * The data is read only while seeding; application requests are served
     * entirely from these local database tables.
     *
     * @throws JsonException
     */
    public function run(): void
    {
        $regions = $this->dataset('regions');
        $provinces = $this->dataset('provinces');
        $cities = $this->dataset('muncities');
        $barangays = $this->dataset('barangays');

        PsgcRegion::query()->upsert(
            array_map(fn (array $region) => [
                'psgc_code' => $region['psgcCode'],
                'region_code' => $region['regCode'],
                'name' => trim($region['regionName']),
                'created_at' => now(),
                'updated_at' => now(),
            ], $regions),
            ['psgc_code'],
            ['region_code', 'name', 'updated_at']
        );

        $regionIds = PsgcRegion::query()->pluck('id', 'region_code')->all();
        $provinceRows = [];

        foreach ($provinces as $province) {
            $key = $province['regCode'].$province['provCode'];
            $provinceRows[$key] = [
                'psgc_code' => $province['psgcCode'],
                'psgc_region_id' => $regionIds[$province['regCode']],
                'province_code' => $province['provCode'],
                'name' => trim($province['provName']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // A few PSGC city records (e.g. Pateros and the Bangsamoro special
        // geographic area) do not have a matching province record. Create a
        // labelled province-equivalent so the four-level cascade remains intact.
        foreach ($cities as $city) {
            $key = $city['regCode'].$city['provCode'];

            if (! isset($provinceRows[$key])) {
                $provinceRows[$key] = [
                    'psgc_code' => $city['regCode'].$city['provCode'].'00000',
                    'psgc_region_id' => $regionIds[$city['regCode']],
                    'province_code' => $city['provCode'],
                    'name' => $this->provinceEquivalentName($city),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk(array_values($provinceRows), 500) as $chunk) {
            PsgcProvince::query()->upsert($chunk, ['psgc_code'], ['psgc_region_id', 'province_code', 'name', 'updated_at']);
        }

        $provinceIds = PsgcProvince::query()->with('region:id,region_code')->get()
            ->mapWithKeys(fn (PsgcProvince $province) => [
                $province->region->region_code.$province->province_code => $province->id,
            ])->all();

        $cityRows = array_map(fn (array $city) => [
            'psgc_code' => $city['psgcCode'],
            'psgc_province_id' => $provinceIds[$city['regCode'].$city['provCode']],
            'city_municipality_code' => $city['munCityCode'],
            'name' => trim($city['munCityName']),
            'created_at' => now(),
            'updated_at' => now(),
        ], $cities);

        foreach (array_chunk($cityRows, 500) as $chunk) {
            PsgcCityMunicipality::query()->upsert($chunk, ['psgc_code'], ['psgc_province_id', 'city_municipality_code', 'name', 'updated_at']);
        }

        $cityCodes = collect($cities)->mapWithKeys(fn (array $city) => [
            $city['regCode'].$city['provCode'].$city['munCityCode'] => $city['psgcCode'],
        ])->all();
        $cityIds = PsgcCityMunicipality::query()->pluck('id', 'psgc_code')->all();

        foreach (array_chunk($barangays, 500) as $chunk) {
            PsgcBarangay::query()->upsert(
                array_map(fn (array $barangay) => [
                    'psgc_code' => $barangay['psgcCode'],
                    'psgc_city_municipality_id' => $cityIds[$cityCodes[$barangay['regCode'].$barangay['provCode'].$barangay['munCityCode']]],
                    'barangay_code' => $barangay['brgyCode'],
                    'name' => trim($barangay['brgyName']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $chunk),
                ['psgc_code'],
                ['psgc_city_municipality_id', 'barangay_code', 'name', 'updated_at']
            );
        }
    }

    /** @return array<int, array<string, string>> */
    private function dataset(string $name): array
    {
        $path = base_path(self::DATA_DIRECTORY.'/'.$name.'.json');

        if (! is_file($path)) {
            throw new \RuntimeException("PSGC data file not found: {$path}");
        }

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, string> $city */
    private function provinceEquivalentName(array $city): string
    {
        return match ($city['regCode'].$city['provCode']) {
            '13817' => 'Pateros (province-equivalent)',
            '19999' => 'Bangsamoro Special Geographic Area (province-equivalent)',
            default => trim($city['munCityName']).' (province-equivalent)',
        };
    }
}
