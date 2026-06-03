<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RealtimeEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RealtimeEventController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $lastEventId = $request->has('last_event_id')
            ? (int) $request->query('last_event_id', 0)
            : (int) RealtimeEvent::query()->max('id');

        return response()->stream(function () use ($lastEventId) {
            $lastId = $lastEventId;
            $startedAt = time();

            echo "id: {$lastId}\n";
            echo "event: krgarage-connected\n";
            echo 'data: ' . json_encode([
                'id' => $lastId,
                'event' => 'connected',
            ]) . "\n\n";
            $this->flushOutput();

            while (!connection_aborted() && (time() - $startedAt) < 30) {
                $events = RealtimeEvent::query()
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(20)
                    ->get();

                if ($events->isNotEmpty()) {
                    foreach ($events as $event) {
                        $lastId = $event->id;

                        echo "id: {$event->id}\n";
                        echo "event: krgarage-change\n";
                        echo 'data: ' . json_encode([
                            'id' => $event->id,
                            'event' => $event->event,
                            'payload' => $event->payload ?? [],
                        ]) . "\n\n";
                    }

                    $this->flushOutput();
                    break;
                }

                usleep(300000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        @flush();
    }
}
