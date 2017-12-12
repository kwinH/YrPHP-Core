<?php
/**
 * Created by PhpStorm.
 * User: TOYOTA
 * Date: 2016/12/12
 * Time: 15:42
 */

namespace YrPHP\Middleware;

use Closure;
use Debug;
use response;
use YrPHP\IMiddleware;
use YrPHP\Request;

class DebugListn implements IMiddleware
{

    public function handler(Request $request, Closure $next)
    {
        if (DEBUG && !$request->isAjax()) {
            Debug::listenSql();
        }

        $next($request);
    }

}