<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (! hash_equals((string) $user->getKey(), (string) $request->route('id'))) {
            abort(403);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403);
        }

        if (! URL::hasValidSignature($request)) {
            $expires = (int) $request->query('expires', 0);
            if ($expires !== 0 && now()->timestamp > $expires) {
                $user->sendEmailVerificationNotification();

                return redirect()->route('verification.notice')
                    ->with('status', 'verification-link-expired');
            }

            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('search-requests.index', absolute: false).'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('search-requests.index', absolute: false).'?verified=1');
    }
}
