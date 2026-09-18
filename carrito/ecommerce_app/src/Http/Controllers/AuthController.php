<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Support\Validator;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login');
    }

    public function login(Request $request): Response
    {
        $validator = (new Validator($request->all()))
            ->required('usuario', 'El usuario')
            ->maxLen('usuario', 20, 'El usuario')
            ->required('clave', 'La contraseña')
            ->maxLen('clave', 200, 'La contraseña');

        if ($validator->fails()) {
            $this->app->session()->flash('error', (string) $validator->firstError());
            return $this->redirect('/login');
        }

        try {
            $this->app->auth()->login(
                trim((string) $request->input('usuario')),
                (string) $request->input('clave'),
                $request->ip(),
                $request->userAgent()
            );
        } catch (ValidationException $e) {
            $this->app->session()->flash('error', $e->getMessage());
            return $this->redirect('/login');
        }

        return $this->redirect('/catalogo');
    }

    public function logout(Request $request): Response
    {
        $this->app->auth()->logout();
        return $this->redirect('/login');
    }
}
