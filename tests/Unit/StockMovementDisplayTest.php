<?php

namespace Tests\Unit;

use App\Models\Medicine;
use App\Models\StockLog;
use App\Support\StockMovementDisplay;
use Tests\TestCase;

class StockMovementDisplayTest extends TestCase
{
    public function test_older_logs_describe_known_activity_without_inventing_balance_changes(): void
    {
        $log = new StockLog(['quantity_added' => 31, 'remarks' => 'Released - Dispensed']);
        $log->setRelation('medicine', new Medicine(['unit' => 'Tablet']));

        $movement = StockMovementDisplay::describe($log);

        $this->assertSame('Dispensed', $movement['label']);
        $this->assertSame('31 tablets', $movement['quantity']);
        $this->assertSame('Stock out', $movement['effect']);
        $this->assertNull($movement['status']);
        $this->assertSame([], $movement['stock_changes']);

        $log->remarks = 'Historical movement with an unknown direction';
        $this->assertNull(StockMovementDisplay::describe($log)['effect']);
    }

    public function test_reservation_and_cancellation_show_which_balance_the_stock_moved_to(): void
    {
        $row = (object) [
            'event_type' => 'reservation', 'movement_type' => 'reservation', 'direction' => null,
            'quantity_added' => 10, 'unit' => 'vial', 'available_delta' => -10, 'allocated_delta' => 10,
            'status_label' => 'Cancelled',
        ];
        $reserved = StockMovementDisplay::describe($row);
        $this->assertSame('Reserved for transfer', $reserved['label']);
        $this->assertSame('Moved from available to reserved stock', $reserved['effect']);
        $this->assertSame('Cancelled', $reserved['status']);

        $row->event_type = $row->movement_type = 'reversal';
        $row->available_delta = 10;
        $row->allocated_delta = -10;
        $row->status_label = 'Cancellation reversal';
        $restored = StockMovementDisplay::describe($row);
        $this->assertSame('Returned from reserved to available stock', $restored['effect']);
        $this->assertSame(['Available stock' => '+10 vials', 'Reserved stock' => '−10 vials'], $restored['stock_changes']);

        $row->allocated_delta = -5;
        $this->assertNull(StockMovementDisplay::describe($row)['quantity']);
    }

    public function test_transfer_direction_and_reserved_dispensing_use_the_confirmed_effect(): void
    {
        $row = (object) [
            'event_type' => 'allocation', 'movement_type' => 'transfer_in', 'direction' => 'addition',
            'quantity_added' => 6, 'unit' => 'mL', 'available_delta' => null, 'allocated_delta' => null,
            'status_label' => 'Active',
        ];
        $receipt = StockMovementDisplay::describe($row);
        $this->assertSame('Received from another facility', $receipt['label']);
        $this->assertSame('6 mL', $receipt['quantity']);

        $row->movement_type = 'transfer_out';
        $row->direction = 'release';
        $this->assertSame('Sent to another facility', StockMovementDisplay::describe($row)['label']);

        $row->event_type = 'dispensing';
        $row->movement_type = 'release';
        $row->available_delta = 0;
        $row->allocated_delta = -6;
        $dispensed = StockMovementDisplay::describe($row);
        $this->assertSame('Dispensed', $dispensed['label']);
        $this->assertSame('Removed from reserved stock', $dispensed['effect']);
        $this->assertSame('No change', $dispensed['stock_changes']['Available stock']);
    }
}
