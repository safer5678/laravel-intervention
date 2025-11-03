<?php

namespace App\Http\Controllers;
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardOrder;
use App\Services\CardGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CardGeneratorController extends Controller
{
    protected $cardGenerator;

    public function __construct(CardGeneratorService $cardGenerator)
    {
        $this->cardGenerator = $cardGenerator;
    }

    /**
     * Generate card from raw data
     * 
     * POST /api/v1/cards/generate
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_name' => 'nullable|string|max:255',
            'width_mm' => 'nullable|integer|min:100|max:500',
            'height_mm' => 'nullable|integer|min:100|max:500',
            'background_color' => 'nullable|string',
            'background_image' => 'nullable|string',
            'elements' => 'required|array',
            'elements.*.type' => 'required|in:text,image,shape',
            'generate_pdf' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create card order
            $cardOrder = CardOrder::create([
                'user_id' => auth()->id(),
                'card_name' => $request->card_name ?? 'Untitled Card',
                'width_mm' => $request->width_mm ?? 150,
                'height_mm' => $request->height_mm ?? 210,
                'background_color' => $request->background_color ?? '#FFFFFF',
                'background_image' => $request->background_image,
                'card_data' => [
                    'elements' => $request->elements,
                    'metadata' => $request->metadata ?? [],
                ],
                'status' => 'draft',
            ]);

            // Generate preview
            $previewUrl = $this->cardGenerator->generatePreview($cardOrder);
            $cardOrder->update(['preview_url' => $previewUrl]);

            // Generate PDF if requested
            if ($request->generate_pdf) {
                $printUrl = $this->cardGenerator->generatePrintFile($cardOrder);
                $cardOrder->update([
                    'print_file_url' => $printUrl,
                    'status' => 'generated',
                ]);
            }
            

            return response()->json([
                'success' => true,
                'message' => 'Card generated successfully',
                'data' => [
                    'order_number' => $cardOrder->order_number,
                    'preview_url' => url($cardOrder->preview_url),
                    'print_file_url' => $cardOrder->print_file_url ? url($cardOrder->print_file_url) : null,
                    'card' => $cardOrder,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate card',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get card details
     */
    public function show($orderNumber)
    {
        $cardOrder = CardOrder::where('order_number', $orderNumber)->first();

        if (!$cardOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cardOrder
        ]);
    }

    /**
     * Upload background image
     */
    public function uploadBackground(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:10240', // 10MB
        ]);

        $path = $request->file('image')->store('backgrounds', 'public');

        return response()->json([
            'success' => true,
            'url' => '/storage/' . $path,
            'full_url' => url('storage/' . $path)
        ]);
    }

    /**
     * Upload user photo
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:10240',
        ]);

        $path = $request->file('photo')->store('photos', 'public');

        return response()->json([
            'success' => true,
            'url' => '/storage/' . $path,
            'full_url' => url('storage/' . $path)
        ]);
    }
}