<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateUserLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            try {
                // Update last_seen tanpa memicu updated_at
                DB::table('pengguna')
                    ->where('id', $user->id)
                    ->update(['last_seen' => Carbon::now()]);
            } catch (\Exception $e) {
                // Log tapi jangan stop request
                \Log::warning('UpdateUserLastSeen error: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
}
