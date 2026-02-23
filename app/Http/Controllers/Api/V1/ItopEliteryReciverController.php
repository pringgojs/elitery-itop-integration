<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessTicketStateChangeJob;
use App\Jobs\ProcessTicketUpdateLogFromElitery;
use Illuminate\Http\Request;

class ItopEliteryReciverController extends Controller
{
    public function ticketUpdatePrivateLog(Request $request)
    {
        $data = $request->all();
        info('Received JSON payload for update private log', $data);
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
        info('update state', $data);
        ProcessTicketStateChangeJob::dispatch($data['id']);
        return response()->json([
            'received' => $data
        ]);
    }
}
