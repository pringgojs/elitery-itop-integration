<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the API key from the request header
        $apiKeyValue = $request->header('X-API-Key');

        // If no API key is provided
        if (!$apiKeyValue) {
            return response()->json([
                'message' => 'API Key is required',
                'error' => 'missing_api_key',
            ], 401);
        }

        // Find the API key in the database
        $apiKey = ApiKey::where('key', $apiKeyValue)
            ->where('is_active', true)
            ->first();

        // If API key is not found or inactive
        if (!$apiKey) {
            return response()->json([
                'message' => 'Invalid API Key',
                'error' => 'invalid_api_key',
            ], 401);
        }

        // Update the last used timestamp
        $apiKey->updateLastUsed();

        // Store the API key in the request for later use
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
