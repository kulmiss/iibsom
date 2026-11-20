<?php

namespace App\Support\Watchdog\Probes;

use Illuminate\Support\Carbon;

/** Reads when the site's https certificate ends (a class of its own so tests can fake the date). */
class Ssl
{
    /** null when it cannot be read (no https, the host does not answer). */
    public function expiresAt(string $host, int $port = 443): ?Carbon
    {
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $client = @stream_socket_client("ssl://{$host}:{$port}", $errno, $error, 8, STREAM_CLIENT_CONNECT, $context);
        if ($client === false) {
            return null;
        }
        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $info = $cert ? openssl_x509_parse($cert) : false;

        return is_array($info) && isset($info['validTo_time_t']) ? Carbon::createFromTimestamp((int) $info['validTo_time_t']) : null;
    }
}
