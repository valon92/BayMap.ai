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
                ['en' => 'Audi A6 2020 white under 180k km', 'sq' => 'Audi A6 2020 e bardhë nën 180 mijë km'],
                ['en' => 'A short psychological thriller book with an unexpected ending', 'sq' => 'Libër thriller psikologjik i shkurtër me fund të papritur'],
                ['en' => 'Vintage flower painting for living room', 'sq' => 'Pikturë vintage me lule për dhomën e ndenjes'],
                ['en' => 'Gaming laptop with long battery and quiet cooling', 'sq' => 'Laptop gaming me bateri të gjatë dhe ftohje të qetë'],
                ['en' => 'Modern Scandinavian sofa under €800', 'sq' => 'Divan skandinav modern nën 800€'],
                ['en' => 'Rolex Submariner authentic pre-owned', 'sq' => 'Rolex Submariner origjinal i përdorur'],
            ],
        ]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => 'Powerbook.ai',
            'tagline' => 'Describe it. Powerbook finds it.',
        ]);
    }
}
