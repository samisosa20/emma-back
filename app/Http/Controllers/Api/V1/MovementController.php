<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
 
use App\Models\Movement;
use App\Models\Category;
use App\Models\Account;

class MovementController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        $movements = Movement::with(['account', 'category', 'event', 'transferOut', 'transferIn', 'investment'])
        ->where([
            ['user_id', $user->id]
        ])
        ->get();

        return response()->json($movements);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try{
            $validator = Validator::make($request->all(), [
                'account_id' => [
                    'required',
                ],
                'category_id' => [
                    'required',
                ],
                'amount' => [
                    'required',
                ],
                'date_purchase' => [
                    'required',
                    'date_format:Y-m-d H:i:s'
                ],
                'type' => [
                    'required'
                ]
            ]);

            if($validator->fails()){
                return response([
                    'message' => 'data missing',
                    'detail' => $validator->errors()
                ], 400)->header('Content-Type', 'json');
            }

            $user = auth()->user();

            if($request->input('type') !== 'transfer') {
                $movement = Movement::create(array_merge($request->input(), ['user_id' => $user->id]));
            } else {
                $validator = Validator::make($request->all(), [
                    'account_end_id' => [
                        'required',
                    ]
                ]);
    
                if($validator->fails()){
                    return response([
                        'message' => 'data missing',
                        'detail' => $validator->errors()
                    ], 400)->header('Content-Type', 'json');
                }

                if($request->input('account_id') === $request->input('account_end_id')) {
                    return response([
                        'message' => 'Amount in cant be iqual to Account out.',
                        'detail' => ''
                    ], 400)->header('Content-Type', 'json');
                }

                $transfer_id = Category::where([
                    ['user_id', $user->id],
                    ['group_id', env('GROUP_TRANSFER_ID')]
                ])
                ->first();
    
                 // Create out move
                 $movement = Movement::create([
                    'account_id' => $request->input('account_id'),
                    'category_id' => $transfer_id->id,
                    'description' => $request->input('description'),
                    'amount' => abs((float)$request->input('amount')) * -1,
                    'trm' => $request->input('amount') / ($request->input('amount_end') ?? $request->input('amount')),
                    'date_purchase' => $request->input('date_purchase'),
                    'user_id' => $user->id,
                ]);
    
                // Create in move
                $movement = Movement::create([
                    'account_id' => $request->input('account_end_id'),
                    'category_id' => $transfer_id->id,
                    'description' => $request->input('description'),
                    'amount' => abs((float)$request->input('amount_end')) > 0.0 ? abs((float)$request->input('amount_end')) : abs((float)$request->input('amount')),
                    'trm' => ($request->input('amount_end') ?? $request->input('amount')) / $request->input('amount'),
                    'date_purchase' => $request->input('date_purchase'),
                    'user_id' => $user->id,
                    'transfer_id' => $movement->id
                ]);
            }


            return response()->json([
                'message' => 'Movimiento creado exitosamente',
                'data' => $movement,
            ]);
        } catch(\Illuminate\Database\QueryException $ex){
            return response([
                'message' =>  'Datos no guardados',
                'detail' => $ex->errorInfo[0]
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = auth()->user();
        $data = Movement::with(['account', 'category', 'event', 'transferOut', 'transferIn', 'investment'])
        ->where([
            ['user_id', $user->id],
            ['id', $id]
        ])
        ->first();
        if($data) {
            return response()->json($data);
        }
        return response([
            'message' =>  'Datos no encontrados',
            'detail' => 'La información no existe'
        ], 400);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Movement  $movement
     * @return \Illuminate\Http\Response
     */
    public function edit(Movement $movement)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Movement  $movement
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Movement $movement)
    {
        try{
            $validator = Validator::make($request->all(), [
                'account_id' => [
                    'required',
                ],
                'category_id' => [
                    'required',
                ],
                'amount' => [
                    'required',
                ],
                'date_purchase' => [
                    'required',
                    'date_format:Y-m-d H:i:s'
                ],
                'type' => [
                    'required'
                ]
            ]);

            if($validator->fails()){
                return response([
                    'message' => 'data missing',
                    'detail' => $validator->errors()
                ], 400)->header('Content-Type', 'json');
            }

            if($request->input('type') === 'move') {
                $movement->fill($request->input())->save();
            } else {
                $validator = Validator::make($request->all(), [
                    'account_end_id' => [
                        'required',
                    ]
                ]);
    
                if($validator->fails()){
                    return response([
                        'message' => 'data missing',
                        'detail' => $validator->errors()
                    ], 400)->header('Content-Type', 'json');
                }

                // if out move
                if(!$movement->transfer_id) {
                    $accountIn = Account::find($request->input('account_end_id'));
                    $accountOut = Account::find($request->input('account_id'));

                    $outData = [
                        'account_id' => $request->input('account_id'),
                        'description' => $request->input('description'),
                        'amount' => abs((float)$request->input('amount')) * -1,
                        'trm' => $accountIn->badge_id !== $accountOut->badge_id ? $request->input('amount') / ($request->input('amount_end') ?? $request->input('amount')) : 1,
                        'date_purchase' => $request->input('date_purchase'),
                    ];
                    $movement->fill($outData)->save();

                    $inData = [
                        'account_id' => $request->input('account_end_id'),
                        'description' => $request->input('description'),
                        'amount' => $accountIn->badge_id !== $accountOut->badge_id ? abs((float)$request->input('amount_end')) : abs((float)$request->input('amount')),
                        'trm' => $accountIn->badge_id !== $accountOut->badge_id ? ($request->input('amount_end') ?? $request->input('amount')) / $request->input('amount') : 1,
                        'date_purchase' => $request->input('date_purchase'),
                    ];
                    $outMovement = Movement::where([
                        ['transfer_id', $movement->id]
                    ])->first();

                    $outMovement ->fill($inData)->save();
                } else {
                    $accountIn = Account::find($request->input('account_id'));
                    $accountOut = Account::find($request->input('account_end_id'));

                    $outData = [
                        'account_id' => $request->input('account_end_id'),
                        'description' => $request->input('description'),
                        'amount' => $accountIn->badge_id !== $accountOut->badge_id ? abs((float)$request->input('amount_end')) : abs((float)$request->input('amount')),
                        'trm' => $accountIn->badge_id !== $accountOut->badge_id ? ($request->input('amount_end') ?? $request->input('amount')) / $request->input('amount') : 1,
                        'date_purchase' => $request->input('date_purchase'),
                    ];
                    $movement->fill($outData)->save();
                    
                    $inData = [
                        'account_id' => $request->input('account_id'),
                        'description' => $request->input('description'),
                        'amount' => abs((float)$request->input('amount')) * -1,
                        'trm' => $accountIn->badge_id !== $accountOut->badge_id ? $request->input('amount') / ($request->input('amount_end') ?? $request->input('amount')) : 1,
                        'date_purchase' => $request->input('date_purchase'),
                    ];
                    $inMovement = Movement::where([
                        ['id', $movement->transfer_id]
                    ])->first();

                    $inMovement ->fill($inData)->save();
                }
            }

            return response()->json([
                'message' => 'Movimiento editado exitosamente',
                'data' => $movement,
            ]);
        } catch(\Illuminate\Database\QueryException $ex){
            return response([
                'message' =>  'Datos no guardados',
                'detail' => $ex->errorInfo[0]
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Movement  $movement
     * @return \Illuminate\Http\Response
     */
    public function destroy(Movement $movement)
    {
        try {
            $user = auth()->user();

            if($movement->transfer_id){
                // if is transfer and is in movevemt so delete out movement
                Movement::find($movement->transfer_id)->delete();
            } else if($movement->category_id === $user->transferId->id) {
                // if is transfer and is out movevemt so delete in movement
                Movement::where([
                    ['transfer_id', $movement->id]
                ])->delete();
            }
            $movement->delete();
            return response()->json([
                'message' => 'Movimiento eliminado exitosamente',
                'data' => $movement,
            ]);
        } catch(\Illuminate\Database\QueryException $ex){
            return response([
                'message' =>  'Datos no guardados',
                'detail' => $ex->errorInfo[0]
            ], 400);
        }
    }

    /**
     * Display a listing of the resource that end_Movement less or equal today.
     *
     * @return \Illuminate\Http\Response
     */
    public function active()
    {
        $user = auth()->user();
        $movements = Movement::where([
            ['user_id', $user->id],
        ])
        ->whereDate('end_Movement', '>=', now())
        ->get();

        return response()->json($movements);
    }
}
