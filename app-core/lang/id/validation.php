<?php

declare(strict_types=1);

/*
| Validation messages in Bahasa Indonesia.
|
| Only the rules the application actually uses are translated; anything else
| falls through to the framework's English defaults, which is loud enough to be
| noticed and translated when it first appears in front of a user.
*/

return [

    'accepted' => ':attribute wajib disetujui.',
    'after' => ':attribute harus berisi tanggal setelah :date.',
    'after_or_equal' => ':attribute harus berisi tanggal setelah atau sama dengan :date.',
    'array' => ':attribute harus berupa larik.',
    'before' => ':attribute harus berisi tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus berisi tanggal sebelum atau sama dengan :date.',
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'date' => ':attribute bukan tanggal yang valid.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
    'email' => ':attribute harus berupa alamat surel yang valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'file' => ':attribute harus berupa berkas.',
    'filled' => ':attribute wajib diisi.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobita.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimes' => ':attribute harus berupa berkas berjenis: :values.',
    'min' => [
        'array' => ':attribute harus memiliki minimal :min item.',
        'file' => ':attribute harus berukuran minimal :min kilobita.',
        'numeric' => ':attribute harus bernilai minimal :min.',
        'string' => ':attribute harus terdiri dari minimal :min karakter.',
    ],
    'numeric' => ':attribute harus berupa angka.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi bila :other bernilai :value.',
    'same' => ':attribute dan :other harus sama.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',

    'attributes' => [
        'nama' => 'nama',
        'email' => 'alamat surel',
        'password' => 'kata sandi',
        'nim' => 'NIM',
        'nidn' => 'NIDN',
        'nik' => 'NIK',
        'sks' => 'SKS',
        'prodi_id' => 'program studi',
        'tahun_akademik_id' => 'tahun akademik',
        'mata_kuliah_id' => 'mata kuliah',
        'kelas_kuliah_id' => 'kelas kuliah',
        'tanggal_lahir' => 'tanggal lahir',
        'jenis_kelamin' => 'jenis kelamin',
    ],

];
