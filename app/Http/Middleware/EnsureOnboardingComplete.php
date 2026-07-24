<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Laisser passer ces routes sans vérification
        if (
            $request->is('onboarding/*') ||
            $request->is('locale') ||          
            $request->is('profile/locale') ||  
            $request->is('logout')
        ) {
            return $next($request);
        }

        // Onboarding pas complété → rediriger
        if (!$user->onboarding_completed_at) {
            return redirect()->route('onboarding.personal-info');
        }

        // Onboarding complété → bloquer l'accès à l'onboarding
        if ($user->onboarding_completed_at && $request->is('onboarding/*')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}