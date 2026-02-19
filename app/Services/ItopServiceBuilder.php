<?php

namespace App\Services;

class ItopServiceBuilder
{
    /* Kirim tiket dari external ke internal */
    public static function payloadTicketCreate(array $fields = [], bool $asJson = false)
    {
        $payload = [
            'operation' => $fields['operation'] ?? 'core/create',
            'comment' => $fields['comment'] ?? 'ticket created from API',
            'class' => $fields['class'] ?? 'UserRequest',
            'output_fields' => $fields['output_fields'] ?? 'id, ref, title, status',
            'fields' => [
                'org_id' => $fields['org_id'] ?? 1,
                'caller_id' => $fields['caller_id'] ?? 2,
                'title' => $fields['title'] ?? 'Create tiket dari API ya gaes',
                'description' => $fields['description'] ?? 'Deskripsi boleh ditulis disini',
                'status' => $fields['status'] ?? 'new',
                'public_log' => ['items' => $fields['public_log'] ?? []],
                'private_log' => ['items' => $fields['private_log'] ?? []],
            ],
        ];

        return $asJson ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $payload;
    }
}