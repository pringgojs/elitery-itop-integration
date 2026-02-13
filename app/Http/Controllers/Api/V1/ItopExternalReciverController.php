<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessTicketUpdateJob;
use Illuminate\Http\Request;
use Pringgojs\LaravelItop\Models\Ticket;

class ItopExternalReciverController extends Controller
{
    /**
     *  $request is json
     */

    public function createTicket(Request $request)
    {
        // Ambil semua data JSON
        $data = $request->all();

        info('Incoming JSON payload', $data);
        $ticket = Ticket::on('itop1')->whereId($data['id'])->first();
        info($ticket);
        return response()->json([
            'received' => $data
        ]);
    }

    public function updateTicket(Request $request)
    {
        // Ambil semua data JSON
        $data = $request->all();
        ProcessTicketUpdateJob::dispatch(Constants::DB_ITOP_EXTERNAL, $data['id']);
        info('Update JSON payload for update queued', $data);
        return response()->json([
            'received' => $data
        ]);
    }
}
