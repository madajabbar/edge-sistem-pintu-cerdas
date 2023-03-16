<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseFormatter;
use App\Models\Access;
use App\Models\Log;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        // Fetch the JSON data from the URL and decode it
        try {
            $jsonurl = "http://pintucerdas.my.id/api/room";
            $json = file_get_contents($jsonurl);
            $data = json_decode($json);
            foreach ($data as $key) {
                Room::updateOrCreate(
                    [
                        'id' => $key->id
                    ],
                    [
                        'name' => $key->name,
                        'slug' => $key->slug,
                        'ip_address' => $key->ip_address,
                    ]
                );
            }

            $jsonurl = "http://pintucerdas.my.id/api/access";
            $json = file_get_contents($jsonurl);
            $data = json_decode($json);
            foreach ($data as $key) {
                Access::updateOrCreate(
                    [
                        'id' => $key->id
                    ],
                    [
                        'name' => $key->name,
                        'start_at' => $key->start_at,
                        'end_at' => $key->end_at,
                        'unique_key' => $key->unique_key,
                        'slug' => $key->slug,
                        'room_id' => $key->room_id,
                    ]
                );
            }

            $jsonurl = "http://pintucerdas.my.id/api/getuser";
            $json = file_get_contents($jsonurl);
            $data = json_decode($json);
            // dd($data);
            foreach ($data as $key) {
                User::updateOrCreate(
                    [
                        'name' => $key->name
                    ],
                    [
                        'name' => $key->name,
                        'unique_key' => $key->unique_key,
                    ]
                );
            }
            return ResponseFormatter::success(null, 'data created successfully');
        } catch (Exception $e) {
            return ResponseFormatter::error(
                [
                    'error' => $e->getMessage(),
                ],
                'some error occurred'
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $ruangan_id = $request->room_id;
            $str = $request->link;
            $expld = explode('-', $str);
            $user = User::where('name', $expld[0])->first();
            // $arr = Access::whereIn('unique_key', $expld)->where('room_id', $ruangan_id)->where('start_at', '<', Carbon::now())->where('end_at', '>', Carbon::now())->first();
            $arr = Access::whereIn('unique_key', $expld)->where('room_id', $ruangan_id)->first();
            // dd($arr);
            if (is_null($arr)) {
                return ResponseFormatter::error(null, 'Invalid QR');
            }

            $url = 'http://pintucerdas.my.id/api/get';
            $data = [
                'access_id' => $arr->id,
                'user_id' => $user->id,
            ];
            // dd($data);
            $statusCode = 0;
            $client = new Client();
            try {
                $response = $client->post($url, [
                    'form_params' => $data
                ]);
                dd($response);
                $statusCode = $response->getStatusCode();
                $check_pending = Log::where('status', 'pending')->get();
                foreach ($check_pending as $key => $value) {
                    Log::where('id', $value->id)->update([
                        'status' => 'success'
                    ]);
                }
                $data = Log::Create(
                    [
                        'access_id' => $arr->id,
                        'user_id' => $user->id,
                        'status' => 'success'
                    ]
                );
                return ResponseFormatter::success($data, 'Upload Successfully');
            } catch (Exception $e) {
                $statusCode = 404;
                return ResponseFormatter::error($e->getMessage(), $statusCode);
            }
            // $responseContent = $response->getBody()->getContents();
            // dd();
            // Do whatever you need to do with the response content

        } catch (Exception $e) {
            return ResponseFormatter::error(
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ],
                'some error occurred'
            );
        }
    }
}
