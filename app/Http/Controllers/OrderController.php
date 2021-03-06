<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Cart;
use Carbon\Carbon;
use Auth;

use App\Mail\OrderMail;
use App\Models\Order;
use App\Models\Bill;
use App\Models\OrderProduct;
use App\Models\Province;
use App\Models\City;
use App\Models\SubDistrict;
use App\Models\Courier;
use App\User;

class OrderController extends Controller
{
    public function index()
    {
        return abort(404);
    }

    public function getSubDistrict($city, $subdistrict)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://pro.rajaongkir.com/api/subdistrict?city=$city&id=$subdistrict",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
                "key: " . env('API_KEY_RAJAONGKIR', null)
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            return json_decode($response, true);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name'    => 'required|max:25',
            'last_name'     => 'required|max:25',
            'recipients'    => 'max:50',
            'province'      => 'required',
            'city'          => 'required',
            'subdistrict'   => 'required',
            'code_courier'  => 'required',
            'name_courier'  => 'required',
            'name_service'  => 'required',
            'street'        => 'required|max:150',
            'postcode'      => 'required|max:10',
            'phone'         => 'required|max:15',
            'email'         => 'required|email',
        ]);
    
        try {
            if (Cart::getTotalQuantity() < 10) {
                return redirect()->back()->with('info', 'Minimal order 10!');
            }
            
            $count      = Order::withTrashed()->count();
            $invoice    = Carbon::now()->format('d/m/Y/') . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            $user = null;

            $dataSubDistrict   = $this->getSubDistrict($request->city, $request->subdistrict);

            $province   = Province::firstOrCreate([
                'id'    => $dataSubDistrict['rajaongkir']['results']['province_id'],
                'name'  => $dataSubDistrict['rajaongkir']['results']['province']
            ]);

            $city   = City::firstOrCreate([
                'id'    => $dataSubDistrict['rajaongkir']['results']['city_id'],
                'name'  => $dataSubDistrict['rajaongkir']['results']['city']
            ]);

            $subdistrict   = SubDistrict::firstOrCreate([
                'id'    => $dataSubDistrict['rajaongkir']['results']['subdistrict_id'],
                'name'  => $dataSubDistrict['rajaongkir']['results']['subdistrict_name']
            ]);
        
            $courier    = Courier::firstOrCreate([
                'code'      => $request->code_courier,
                'name'      => $request->name_courier,
                'service'   => $request->name_service
            ]);

            $order = Order::create([
                'id'            => Str::uuid(),
                'user_id'       => Auth::id(),
                'invoice'       => $invoice,
                'first_name'    => $request->first_name,
                'last_name'     => $request->last_name,
                'recipients'    => $request->recipients_name,
                'province_id'   => $province->id,
                'city_id'       => $city->id,
                'subdistrict_id'=> $subdistrict->id,
                'street'        => $request->street,
                'postcode'      => $request->postcode,
                'phone'         => $request->phone,
                'email'         => $request->email,
            ]);
        
            $items  = Cart::getContent();
        
            foreach ($items as $item) {
                OrderProduct::create([
                    'id'            => Str::uuid(),
                    'order_id'      => $order->id,
                    'product_id'    => $item->id,
                    'quantity'      => $item->quantity,
                    'special_price' => $item->price
                ]);
            }

            $shipping = $order->cost($courier->code, $courier->service);

            Bill::create([
                'id'        => Str::uuid(),
                'order_id'  => $order->id,
                'courier_id'=> $courier->id,
                'shipping'  => $order->cost($courier->code, $courier->service),
                'weight'    => $order->weight(),
                'total'     => $order->total() + $shipping
            ]);
    
            $items  = Cart::clear();
    
            Mail::to($order->email)->send(new OrderMail($order));
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', $e->getMessage());
        }
    
        return view('pages.admin.order.index', compact('order'));
    }
}
