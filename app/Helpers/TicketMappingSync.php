<?php

namespace App\Helpers;

use App\Models\TicketMapping;

class TicketMappingSync
{
    public static function sync($externalTicketId, $internalTicketId, $ticketClass, $isStopSync = false)
    {
        $mapping = TicketMapping::where('external_ticket_id', $externalTicketId)->first();

        if (!$mapping) {
            $mapping = new TicketMapping();
            $mapping->external_ticket_id = $externalTicketId;
        }

        $mapping->elitery_ticket_id = $internalTicketId;
        $mapping->ticket_class = $ticketClass;
        $mapping->last_sync_at = now();
        $mapping->is_stop_sync = $isStopSync;
        $mapping->save();

        return $mapping;
    }
}