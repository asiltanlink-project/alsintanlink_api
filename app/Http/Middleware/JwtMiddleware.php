<?php

namespace App\Http\Middleware;

use Closure;
use JWTAuth;
  use Exception;
  use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
      try {

                $user = JWTAuth::parseToken()->authenticate();
                } catch (Exception $e) {
                    if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                        $final = array('message' => 'Token is Invalid');
                        return response()->json(['status' => -1, 'result' => $final],403);
                    }else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                        $final = array('message' => 'Token is Expired');
                        return response()->json(['status' => -1, 'result' => $final],403);
                    }else{
                        $final = array('message' => 'Authorization Token not found');
                        return response()->json(['status' => -1, 'result' => $final],403);

                    }
                }
          return $next($request);
    }
}
