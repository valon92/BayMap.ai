<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class MetaController extends Controller
{
    public function trending(): JsonResponse
    {
        $path = storage_path('data/trending.json');
        $data = File::exists($path)
            ? json_decode(File::get($path), true)
            : [];

        return response()->json(['trending' => $data]);
    }

    public function examples(): JsonResponse
    {
        return response()->json([
            'examples' => [
                [
                    'id' => 'travel_train',
                    'category' => 'travel',
                    'icon' => '🚆',
                    'en' => 'Train ticket Paris to Zurich tomorrow afternoon',
                    'sq' => 'Bilet tren Paris–Zurich nesër paradite',
                ],
                [
                    'id' => 'travel_flight',
                    'category' => 'travel',
                    'icon' => '✈️',
                    'en' => 'Round-trip flight Pristina to Geneva next week',
                    'sq' => 'Bilet avioni kthyse Prishtinë–Geneva javën e ardhshme',
                ],
                [
                    'id' => 'car',
                    'category' => 'automotive',
                    'icon' => '🚗',
                    'en' => 'Audi A6 2020 white under 180k km',
                    'sq' => 'Audi A6 2020 e bardhë nën 180 mijë km',
                ],
                [
                    'id' => 'book',
                    'category' => 'book',
                    'icon' => '📚',
                    'en' => 'Short psychological thriller with an unexpected ending',
                    'sq' => 'Libër thriller psikologjik i shkurtër me fund të papritur',
                ],
                [
                    'id' => 'electronics',
                    'category' => 'electronics',
                    'icon' => '💻',
                    'en' => 'Gaming laptop with long battery and quiet cooling',
                    'sq' => 'Laptop gaming me bateri të gjatë dhe ftohje të qetë',
                ],
                [
                    'id' => 'fashion',
                    'category' => 'fashion',
                    'icon' => '👟',
                    'en' => 'White Nike Air Max 90 size 43',
                    'sq' => 'Nike Air Max 90 të bardha numër 43',
                ],
                [
                    'id' => 'furniture',
                    'category' => 'furniture',
                    'icon' => '🛋️',
                    'en' => 'Modern Scandinavian sofa under €800',
                    'sq' => 'Divan skandinav modern nën 800€',
                ],
                [
                    'id' => 'painting',
                    'category' => 'painting',
                    'icon' => '🎨',
                    'en' => 'Vintage flower painting for the living room',
                    'sq' => 'Pikturë vintage me lule për dhomën e ndenjes',
                ],
                [
                    'id' => 'luxury',
                    'category' => 'luxury',
                    'icon' => '⌚',
                    'en' => 'Authentic pre-owned Rolex Submariner',
                    'sq' => 'Rolex Submariner origjinal i përdorur',
                ],
            ],
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => 'BuyMap.ai',
            'tagline' => 'Describe it. BuyMap finds it.',
        ]);
    }
}
