<?php

namespace App\Http\Controllers;

use App\Http\Resources\CashResource;
use App\Models\Cash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CashController extends Controller
{
    public function index()
    {
        $from = request('from');
        $to = request('to');

        if($from && $to) {
            $debit = $this->_getBalance($from, $to, ">=");
            $credit = $this->_getBalance($from, $to, "<");
            $transactions = auth()->user()->cashes()->whereBetween('when', [$from, $to])->latest()->get();
        } else {
            $debit = $this->_getBalance(now()->firstOfMonth(), now(), ">=");
            $credit = $this->_getBalance(now()->firstOfMonth(), now(), "<");
            $transactions = auth()->user()->cashes()->whereBetween('when', [now()->firstOfMonth(), now()])->latest()->get();
        }

        return response()->json([
            'balances' => formatPrice(auth()->user()->cashes()->get('amount')->sum('amount')),
            'debit' => formatPrice($debit),
            'credit' => formatPrice($credit),
            'transactions' => CashResource::collection($transactions),
            'now' => now()->format("Y-m-d"),
            'firstOfMonth' => now()->firstOfMonth()->format("Y-m-d"),
        ]);
    }

    public function store()
    {
        request()->validate([
            'name' => 'required',
            'amount' => 'required|numeric',
        ]);
        
        try {
            DB::beginTransaction();
            $cash = auth()->user()->cashes()->create([
                'name' => request('name'),
                'slug' => Str::slug(request('name') .'-'. Str::random(6)),
                'when' => request('when') ?? now(),
                'amount' => request('amount'),
                'description' => request('description'),
            ]);
            DB::commit();

            return response()->json([
                'message' => 'The transaction has been saved.',
                'cash' => new CashResource($cash),
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            //throw $th;

            return response()->json([
                'message' => 'something went wrong on storing transaction.',
            ]);
        }
    }

    public function show(Cash $cash)
    {
        $this->authorize('show', $cash);

        return new CashResource($cash); 
    }

    public function _getBalance($from, $to, $operator)
    {
        return auth()->user()->cashes()
            ->whereBetween('when', [$from, $to])
            ->where('amount', $operator, 0)
            ->get('amount')
            ->sum('amount');
    }
}
