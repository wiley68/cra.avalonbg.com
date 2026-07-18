<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Support\Translations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UsersXlsxExportService
{
    public function writeToFile(Organization $organization, string $absolutePath): int
    {
        $rows = $this->buildRows($organization);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Translations::get('users.export.sheet_title'));

        $sheet->fromArray($rows, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A:F')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return max(0, count($rows) - 1);
    }

    /**
     * @return list<list<int|string|null>>
     */
    public function buildRows(Organization $organization): array
    {
        $headings = [
            Translations::get('users.export.sheet_columns.id'),
            Translations::get('users.export.sheet_columns.name'),
            Translations::get('users.export.sheet_columns.email'),
            Translations::get('users.export.sheet_columns.role'),
            Translations::get('users.export.sheet_columns.must_change_password'),
            Translations::get('users.export.sheet_columns.created_at'),
        ];

        $dataRows = $this->exportableUsers($organization)
            ->map(fn (object $user): array => $this->mapUser($user))
            ->values()
            ->all();

        return array_merge([$headings], $dataRows);
    }

    /**
     * @return Collection<int, object{
     *     id: int,
     *     name: string,
     *     email: string,
     *     must_change_password: bool|int,
     *     role_slug: string,
     *     role_name: string,
     *     created_at: Carbon|null
     * }>
     */
    public function exportableUsers(Organization $organization): Collection
    {
        return User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.must_change_password',
                'users.created_at',
                'roles.slug as role_slug',
                'roles.name as role_name',
            ])
            ->join('organization_user', 'organization_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'organization_user.role_id')
            ->where('organization_user.organization_id', $organization->id)
            ->orderBy('users.id')
            ->get();
    }

    /**
     * @param  object{
     *     id: int,
     *     name: string,
     *     email: string,
     *     must_change_password: bool|int,
     *     role_slug: string,
     *     role_name: string,
     *     created_at: Carbon|null
     * }  $user
     * @return list<int|string>
     */
    private function mapUser(object $user): array
    {
        $roleKey = 'roles.'.$user->role_slug;
        $roleLabel = Translations::get($roleKey);
        if ($roleLabel === $roleKey) {
            $roleLabel = (string) $user->role_name;
        }

        return [
            $user->id,
            $user->name,
            $user->email,
            $roleLabel,
            (bool) $user->must_change_password
            ? Translations::get('common.yes')
            : Translations::get('common.no'),
            $user->created_at?->format('d.m.Y H:i') ?? '',
        ];
    }
}
