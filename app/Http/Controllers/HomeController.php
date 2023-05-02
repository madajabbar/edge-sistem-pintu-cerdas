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
use Illuminate\Support\Facades\Artisan;

class HomeController extends Controller
{
    public function index()
    {
        // Fetch the JSON data from the URL and decode it
        try {
            Artisan::call('migrate:fresh');
            $jsonurl = "http://pintucerdas.my.id/api/room";
            $json = file_get_contents($jsonurl);
            $room = json_decode($json);
            foreach ($room as $key) {
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
            $access = json_decode($json);
            foreach ($access as $key) {
                Access::updateOrCreate(
                    [
                        'id' => $key->id
                    ],
                    [
                        'name' => $key->name,
                        'day' => $key->day,
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
            $user = json_decode($json);
            foreach ($user as $key) {
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
            return ResponseFormatter::success([
                'room' => $room,
                'access' => $access,
                'user' => $user,
            ], 'data created successfully');
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
            if($user ? $user->name == 'admin' : false){
                $access = Access::where('room_id', $ruangan_id)->first();
                $url = 'http://pintucerdas.my.id/api/get';
                $data = [
                    'access_id' => $access->id,
                    'user_id' => $user->id,
                ];
                // dd($data);
                $statusCode = 0;
                $client = new Client();
                try {
                    $response = $client->post($url, [
                        'form_params' => $data
                    ]);
                    $data = Log::Create(
                        [
                            'access_id' => $access->id,
                            'user_id' => $user->id,
                            'status' => 'success'
                        ]
                    );
                    $statusCode = $response->getStatusCode();
                    $check_pending = Log::where('status', 'pending')->get();
                    foreach ($check_pending as $key => $value) {
                        $data = [
                            'access_id' => $value->access_id,
                            'user_id' => $value->user_id,
                        ];
                        $client->post($url, [
                            'form_params' => $data
                        ]);
                        Log::where('id', $value->id)->update([
                            'status' => 'success'
                        ]);
                    }
                    return ResponseFormatter::success($data, 'Upload Successfully');
                } catch (Exception $e) {
                    $data = Log::Create(
                        [
                            'access_id' => $access->id,
                            'user_id' => $user->id,
                            'status' => 'pending'
                        ]
                    );
                    return ResponseFormatter::success($data, 'Upload Success but cloud server has trouble');
                }
            }
            if($user == null){
                return ResponseFormatter::error(null,'User Not Found');
            }
            // $arr = Access::whereIn('unique_key', $expld)->where('room_id', $ruangan_id)->where('day', Carbon::now()->format('l'))->where('start_at', '<', Carbon::now())->where('end_at', '>', Carbon::now())->first();
            $arr = Access::whereIn('unique_key', $expld)->where('room_id', $ruangan_id)->first();
            // dd($arr);

            if (is_null($arr)) {
                return ResponseFormatter::error(null, 'Invalid QR');
            }
            if ($arr->day == Carbon::now()->format('l') && (Carbon::now()->format('H:i:s') >= $arr->start_at && Carbon::now()->format('H:i:s') <= $arr->end_at)) {
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
                    $data = Log::Create(
                        [
                            'access_id' => $arr->id,
                            'user_id' => $user->id,
                            'status' => 'success'
                        ]
                    );
                    $statusCode = $response->getStatusCode();
                    $check_pending = Log::where('status', 'pending')->get();
                    foreach ($check_pending as $key => $value) {
                        $data = [
                            'access_id' => $value->access_id,
                            'user_id' => $value->user_id,
                        ];
                        $client->post($url, [
                            'form_params' => $data
                        ]);
                        Log::where('id', $value->id)->update([
                            'status' => 'success'
                        ]);
                    }
                    return ResponseFormatter::success($data, 'Upload Successfully');
                } catch (Exception $e) {
                    $data = Log::Create(
                        [
                            'access_id' => $arr->id,
                            'user_id' => $user->id,
                            'status' => 'pending'
                        ]
                    );
                    return ResponseFormatter::success($data, 'Upload Success but cloud server has trouble');
                }
                // $responseContent = $response->getBody()->getContents();
                // dd();
                // Do whatever you need to do with the response content
            }
            else{
                return ResponseFormatter::error(
                    'Invalid QR', 'You has no access in this room yet'
                );
            }
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
