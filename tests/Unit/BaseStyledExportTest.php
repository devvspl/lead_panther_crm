<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Exports\BaseStyledExport;
use Illuminate\Support\Collection;

class BaseStyledExportTest extends TestCase
{
    public function test_mapping_handles_column_names_matching_global_functions(): void
    {
        $dummyRow = (object) [
            'id' => 101,
            'action' => 'user.login',
            'date' => '2026-10-22',
            'time' => '14:30:00',
            'nested' => ['foo' => 'bar'],
        ];

        $export = new BaseStyledExport(
            data: collect([$dummyRow]),
            title: 'Test Export',
            headings: ['ID', 'Action', 'Date', 'Time', 'Nested', 'Custom'],
            columns: [
                'id',
                'action', // matches global Laravel helper action()
                'date',   // matches global PHP function date()
                'time',   // matches global PHP function time()
                'nested',
                fn($r) => 'computed-' . $r->id,
            ]
        );

        $mapped = $export->map($dummyRow);

        $this->assertEquals(101, $mapped[0]);
        $this->assertEquals('user.login', $mapped[1]);
        $this->assertEquals('2026-10-22', $mapped[2]);
        $this->assertEquals('14:30:00', $mapped[3]);
        $this->assertEquals(json_encode(['foo' => 'bar']), $mapped[4]);
        $this->assertEquals('computed-101', $mapped[5]);
    }
}
