<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessTicketCreateJob;
use App\Jobs\ProcessTicketUpdateJob;
use Illuminate\Http\Request;

class ItopExternalReciverController extends Controller
{
    /**
     *  $request is json
     */
    public function createTicket(Request $request)
    {
        $data = $request->all();
        ProcessTicketCreateJob::dispatch($data['id']);
        info('Created JSON payload for create queued', $data);
        return response()->json([
            'received' => $data
        ]);
    }

    public function updateTicket(Request $request)
    {
        $data = $request->all();
        ProcessTicketUpdateJob::dispatch(env('DB_CONNECTION_ITOP_EXTERNAL'), $data['id']);
        info('Update JSON payload for update queued', $data);
        return response()->json([
            'received' => $data
        ]);
    }

    public function createAttachment(Request $request)
    {
        $data = $request->all();
        info('Received JSON payload for create attachment', $data);
        return response()->json([
            'received' => $data
        ]);
    }
}
