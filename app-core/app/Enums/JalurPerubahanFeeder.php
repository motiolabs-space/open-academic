<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a locked-column change reaches PDDIKTI.
 *
 * The web service takes the enrolment-record columns through
 * UpdateRiwayatPendidikanMahasiswa, keyed on id_registrasi_mahasiswa. The
 * identity columns on the biodata record and every exit column are refused
 * there and go through PDDIKTI admin with a letter and attachments.
 */
enum JalurPerubahanFeeder: string
{
    case WebService = 'web_service';
    case PddiktiAdmin = 'pddikti_admin';

    public function label(): string
    {
        return match ($this) {
            self::WebService => 'Web service',
            self::PddiktiAdmin => 'PDDIKTI admin',
        };
    }
}
