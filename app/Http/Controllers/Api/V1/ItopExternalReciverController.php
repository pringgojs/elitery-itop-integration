<?php

namespace App\Http\Controllers\Api\V1;

use App\Constants\Constants;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessAttachmentUpdateJob;
use App\Jobs\ProcessTicketCreateJob;
use App\Jobs\ProcessTicketUpdateJob;
use App\Jobs\ProcessTicketUpdateLogFromExternal;
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
        info('Created JSON payload for create ticket queued', $data);
        return response()->json([
            'received' => $data
        ]);
    }

    public function updateTicket(Request $request)
    {
        $data = $request->all();
        ProcessTicketUpdateJob::dispatch($data['id']);
        info('Update JSON payload for update ticket queued', $data);
        return response()->json([
            'received' => $data
        ]);
    }

    public function ticketUpdatePrivateLog(Request $request)
    {
        $data = $request->all();
        info('Received JSON payload for update private log', $data);
        ProcessTicketUpdateLogFromExternal::dispatch($data['id']);
        return response()->json([
            'received' => $data
        ]);
    }

    // dipanggil ketika user melakukan upload Attachment, dimana itu masih bersifat temporary
    public function createAttachment(Request $request)
    {
        $data = $request->all();
        info('Received JSON payload for create attachment', $data);
        return response()->json([
            'received' => $data
        ]);
    }

    public function deleteAttachment(Request $request)
    {
        $data = $request->all();
        info('Received JSON payload for delete attachment', $data);
        return response()->json([
            'received' => $data
        ]);
    }

    public function updateAttachment(Request $request)
    {
        $data = $request->all();
        info('Received JSON payload for update attachment', $data);
        ProcessAttachmentUpdateJob::dispatch($data['source'], $data['item_id'], $data['item_class']);
        return response()->json([
            'received' => $data
        ]);
    }
}
