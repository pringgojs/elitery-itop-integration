<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
        return response()->json([
            'received' => $data
        ]);
    }

    public function updateTicket(Request $request)
    {
        // Ambil semua data JSON
        $data = $request->all();

        info('update JSON payload', $data);
        return response()->json([
            'received' => $data
        ]);
    }
}
