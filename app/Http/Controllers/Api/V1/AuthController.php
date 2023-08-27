<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Auth\Events\Registered;
use App\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\Category;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try{

            $validator = Validator::make($request->all(), [
                'password' => [
                    'required',
                ],
                'email' => [
                    'required',
                    'email'
                ],
            ]);

            if($validator->fails()){
                return response([
                    'message' => 'data missing',
                    'detail' => $validator->errors()
                ], 400)->header('Content-Type', 'json');
            }

            $credentials = $request->only('email', 'password');

            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'message' => 'Usuario o contraseña incorrecta'
                ], 401);
            }

            $user = JWTAuth::user();

            return response()->json([
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'transfer_id' => $user->transferId->id,
                    'currency' => $user->badge_id,
                ],
                'token' => $token
            ]);

        } catch(\Illuminate\Database\QueryException $ex){
            return response([
                'message' =>  $ex->errorInfo[0] === "23000" ? 'Usuario registrado' : 'Datos no guardados',
                'detail' => $ex->errorInfo[0]
            ], 400);
        }
    }

    public function register(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                ],
                'email' => [
                    'required',
                    'email'
                ],
                'password' => [
                    'required'
                ],
            ]);

            if($validator->fails()){
                return response([
                    'message' => 'data missing',
                    'detail' => $validator->errors()
                ], 400)->header('Content-Type', 'json');
            }

            $user = new User([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->input('password'))
            ]);
            $user->save();

            Category::create([
                'name' => 'Transferencia',
                'group_id' => env('GROUP_TRANSFER_ID') ?? 1,
                'user_id' => $user->id
            ]);

            $token = JWTAuth::fromUser($user);
            event(new Registered($user));

            return response()->json([
                'message' => 'Usuario registrado exitosamente',
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email
                ],
                'token' => $token
            ], 201);
        } catch(\Illuminate\Database\QueryException $ex){
            return response([
                'message' =>  $ex->errorInfo[0] === "23000" ? 'Usuario registrado' : 'Datos no guardados',
                'detail' => $ex->errorInfo[0]
            ], 400);
        }
    }

    public function resetPassword(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'email' => [
                    'required',
                    'email'
                ],
            ]);

            if($validator->fails()){
                return response([
                    'message' => 'data missing',
                    'detail' => $validator->errors()
                ], 400)->header('Content-Type', 'json');
            }

            $user = User::where([
                ['email', $request->email]
            ])->first();

            if($user){
                $temp_pass = Str::random(10);
                $user->password = Hash::make($temp_pass);
                $user->save();

                $user->notify(new PasswordResetNotification($temp_pass));
            } else {
                return response()->json([
                   'message' => 'Usuario no registrado'
                ], 400);
            }



            return response()->json([
                'message' => 'Te Llegara una email, con mas informacion',
                'data' => [],
            ]);
        } catch(\Illuminate\Database\QueryException $ex){
            return response([
                'message' =>  'Datos no guardados',
                'detail' => $ex->errorInfo[0]
            ], 400);
        }
    }

    public function logout(Request $request)
    {
        auth()->logout();

        return response()->json(['message' => 'Se cerro la sesion exitosamente']);
    }
}