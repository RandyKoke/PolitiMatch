<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const GENERIC_MESSAGE = 'Si un compte existe avec cet email, un lien de réinitialisation vient de lui être envoyé.';

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        // Toujours la même réponse, que l'email soit connu ou non (cf.
        // ForgotPasswordRequest) : le comportement observable de l'API ne
        // doit jamais permettre de deviner quels emails sont enregistrés.
        if ($user !== null) {
            // Un seul jeton actif à la fois : les demandes précédentes non
            // utilisées sont invalidées plutôt que laissées traîner en base.
            PasswordResetToken::where('user_id', $user->id)->whereNull('used_at')->delete();

            $rawToken = Str::random(64);

            PasswordResetToken::create([
                'user_id' => $user->id,
                'token_hash' => Hash::make($rawToken),
                'expires_at' => now()->addMinutes(60),
            ]);

            $user->notify(new ResetPasswordNotification($rawToken));
        }

        return response()->json(['message' => self::GENERIC_MESSAGE]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();
        $invalidResponse = response()->json(['message' => 'Ce lien de réinitialisation est invalide ou a expiré.'], 422);

        if ($user === null) {
            return $invalidResponse;
        }

        $candidate = PasswordResetToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn (PasswordResetToken $token) => Hash::check($request->string('token'), $token->token_hash));

        if ($candidate === null) {
            return $invalidResponse;
        }

        $user->update(['password_hash' => Hash::make($request->string('password'))]);
        $candidate->update(['used_at' => now()]);

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }
}
