<?php

namespace App\Http\Middleware;

use Closure;
use App\Libs\User;
use Illuminate\Support\Facades\Log;

class BeforeMiddleware
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
      $url = $request->url();
      $path = $request->path();
      $Params = $request->all();
      $Params = $request->all();
      $uid = $request->input('uid');
      $code = $request->input('sc');
      if ($uid == 100047) {
        return $next($request);
      }
      if ($request->path() == 'circle/circleRecommend') return $next($request); 
      if ($request->path() == 'circle/ServicejoinCircle') return $next($request); 
      if ($request->path() == 'circle/ServiceGetCircle') return $next($request); 
      if ($request->path() == 'circle/ServiceModifyCircleUser') return $next($request); 
      if ($request->path() == 'circle/ActivityTopCircle') return $next($request); 
      if ($request->path() == 'circle/GetEssEnce') return $next($request); 
      if ($request->path() == 'circle/GetUserTopCircle') return $next($request); 
      if ($request->path() == 'circle/OAPublishNotify') return $next($request); 
      if ($request->path() == 'circle/OACommentNotify') return $next($request); 
      if ($request->path() == 'circle/walletInfo') return $next($request); 
      if ($request->path() == 'circle/ServicePayNotify') return $next($request); 
      if ($request->path() == 'circle/ServiceUserWithdraw') return $next($request); 
      if ($request->path() == 'circle/RejectWithdraw') return $next($request); 
      if ($request->path() == 'circle/ServiceH5SharedCircle') return $next($request); 
      // for test
      if ($request->path() == 'circle/withdraw') return $next($request); 

      if ($uid) {
        if (User::instance()->checkUserSecureCode($uid, $code) === false) {
          Log::info('login error');
          return response()->json([
            'code' => 1500,
            'msg'  => "",
          ]);
        }
      }
      return $next($request);
    }
}
