<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\JWTException;

class RefreshToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            try {
                $newToken = JWTAuth::refresh(JWTAuth::getToken());
                JWTAuth::setToken($newToken);
                $request->headers->set('Authorization', 'Bearer ' . $newToken);
            } catch (JWTException $e) {
                return response()->json(['error' => 'Token cannot be refreshed'], 401);
            }
        }

        return $next($request);
    }
}
