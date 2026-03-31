<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessTicketStateChangeJob;
use App\Jobs\ProcessTicketUpdateLogFromElitery;
use App\Models\TicketMapping;
use Illuminate\Http\Request;

class ItopEliteryReciverController extends Controller
{
    public function ticketUpdatePrivateLog(Request $request)
    {
        $data = $request->all();
        info('ELITERY - update private log', $data);
        $mapping = TicketMapping::where('elitery_ticket_id', $data['id'])->first();
        if (! $mapping) {
            info("No mapping found for ticket id: " . $data['id']);
            return response()->json([
                'message' => 'No mapping found for ticket id: ' . $data['id']
            ], 404);
        }
        
        ProcessTicketUpdateLogFromElitery::dispatch($data['id']);
        return response()->json([
            'received' => $data
        ]);
    }
    /**
     *  $request is json
     */
    public function ticketStateChange(Request $request)
    {
        $data = $request->all();
        info('ELITERY - update state', $data);
        $mapping = TicketMapping::where('elitery_ticket_id', $data['id'])->first();
        if (! $mapping) {
            info("No mapping found for ticket id: " . $data['id']);
            return response()->json([
                'message' => 'No mapping found for ticket id: ' . $data['id']
            ], 404);
        }

        ProcessTicketStateChangeJob::dispatch($data['id']);
        return response()->json([
            'received' => $data
        ]);
    }
}
