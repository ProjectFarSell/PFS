<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\MobileCartService;
use App\Services\Mobile\Replay;
use App\Services\Mobile\Reply;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(MobileRequest $request, MobileCartService $cart)
    {
        return Reply::data($cart->view($cart->get($request->user())));
    }

    public function add(MobileRequest $request, MobileCartService $service)
    {
        $data = $request->validated();

        return Reply::data($service->mutate($request->user(), function ($cart) use ($data, $service) {
            $service->version($cart, $data['cart_version']);
            $service->add($cart, $data);
        }));
    }

    public function quantity(MobileRequest $request, string $line_id, MobileCartService $service)
    {
        $data = $request->validated();

        return Reply::data($service->mutate($request->user(), function ($cart) use ($data, $line_id, $service) {
            $service->version($cart, $data['cart_version']);
            $lines = $cart->lines;
            $key = collect($lines)->search(fn ($line) => $line['id'] === $line_id);
            abort_if($key === false, 404);
            if ($data['qty'] === 0) {
                unset($lines[$key]);
            } else {
                $current = $service->hydrate($cart)->firstWhere('id', $line_id);
                if (! $current->valid || $data['qty'] > $current->stock) {
                    throw new ApiFailure('stock_conflict', 'This quantity is no longer available.');
                }
                $lines[$key]['qty'] = $data['qty'];
            }
            $cart->update(['lines' => $lines, 'version' => $cart->version + 1]);
        }));
    }

    public function merge(MobileRequest $request, MobileCartService $service, Replay $replay)
    {
        $data = $request->validated();

        return $replay->run($request, $data, function () use ($request, $data, $service) {
            $rejected = [];
            $view = $service->mutate($request->user(), function ($cart) use ($data, $service, &$rejected) {
                $service->version($cart, $data['cart_version']);
                foreach ($data['lines'] as $index => $line) {
                    try {
                        $service->add($cart, $line);
                    } catch (ApiFailure|ValidationException|ModelNotFoundException $exception) {
                        $rejected[] = ['index' => $index, 'code' => 'line_unavailable', 'message' => 'This line could not be added. Check its options and stock.'];
                    }
                }
            });

            return Reply::data(['cart' => $view, 'rejected_lines' => $rejected]);
        }, $data['merge_id'], 'guest-cart-merge');
    }
}
