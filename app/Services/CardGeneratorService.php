<?php

namespace App\Services;

use App\Models\CardOrder;
use Intervention\Image\Laravel\Facades\Image;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class CardGeneratorService
{
    /**
     * Generate preview image (PNG)
     */
    public function generatePreview(CardOrder $cardOrder)
    {
        $dpi = 150;
        $width = (int)($cardOrder->width_mm * $dpi / 25.4);
        $height = (int)($cardOrder->height_mm * $dpi / 25.4);

        // ✅ v3 canvas method
        $canvas = Image::create($width, $height)->fill($cardOrder->background_color);

        if ($cardOrder->background_image) {
            $bgPath = public_path($cardOrder->background_image);
            if (file_exists($bgPath)) {
                $bgImage = Image::read($bgPath)->resize($width, $height);
                // ✅ v3 insert → place
                $canvas->place($bgImage, 'top-left', 0, 0);
            }
        }

        foreach ($cardOrder->card_data['elements'] as $element) {
            $this->addElementToCanvas($canvas, $element, $dpi);
        }

        $filename = 'preview_' . $cardOrder->order_number . '.png';
        $savePath = storage_path('app/public/previews/' . $filename);

        if (!file_exists(dirname($savePath))) {
            mkdir(dirname($savePath), 0755, true);
        }

        $canvas->save($savePath, quality: 80);

        return '/storage/previews/' . $filename;
    }


    /**
     * Generate print-ready PDF
     */
    public function generatePrintFile(CardOrder $cardOrder)
    {
        $dpi = 300;
        $width = (int)($cardOrder->width_mm * $dpi / 25.4);
        $height = (int)($cardOrder->height_mm * $dpi / 25.4);

        $canvas = Image::create($width, $height)->fill($cardOrder->background_color);

        if ($cardOrder->background_image) {
            $bgPath = public_path($cardOrder->background_image);
            if (file_exists($bgPath)) {
                $bgImage = Image::read($bgPath)->resize($width, $height);
                $canvas->place($bgImage, 'top-left', 0, 0);
            }
        }

        foreach ($cardOrder->card_data['elements'] as $element) {
            $this->addElementToCanvas($canvas, $element, $dpi);
        }

        $filename = 'print_' . $cardOrder->order_number . '.jpg';
        $savePath = storage_path('app/public/print_files/' . $filename);

        if (!file_exists(dirname($savePath))) {
            mkdir(dirname($savePath), 0755, true);
        }

        $canvas->save($savePath, quality: 100);

        $pdfFilename = 'print_' . $cardOrder->order_number . '.pdf';
        $this->convertImageToPdf($savePath, storage_path('app/public/print_files/' . $pdfFilename));

        return '/storage/print_files/' . $pdfFilename;
    }


    /**
     * Add element to canvas
     */
    protected function addElementToCanvas($canvas, $element, $dpi)
    {
        $scale = $dpi / 25.4;

        if ($element['type'] === 'text') {
            $this->addTextElement($canvas, $element, $scale);
        } elseif ($element['type'] === 'image') {
            $this->addImageElement($canvas, $element, $scale);
        } elseif ($element['type'] === 'shape') {
            $this->addShapeElement($canvas, $element, $scale);
        }
    }


    /**
     * Text element
     */
    protected function addTextElement($canvas, $element, $scale)
    {
        $x = $element['x'] * $scale;
        $y = $element['y'] * $scale;
      $fontSize = (int)$element['font_size'];

        $fontPath = $this->getFontPath($element['font_family'] ?? 'Arial');

        $canvas->text($element['text'], $x, $y, function ($font) use ($fontSize, $fontPath, $element) {
            if (file_exists($fontPath)) $font->file($fontPath);
            $font->size($fontSize);
            $font->color($element['color'] ?? '#000');
        });
    }


    /**
     * Image element
     */
    protected function addImageElement($canvas, $element, $scale)
    {
        $imagePath = public_path($element['src']);
        if (!file_exists($imagePath)) return;

        $img = Image::read($imagePath);

        if (isset($element['width']) && isset($element['height'])) {
            $img->resize($element['width'] * $scale, $element['height'] * $scale);
        }

        if (isset($element['rotation'])) {
            $img->rotate(-$element['rotation']);
        }

        $canvas->place($img, 'top-left', $element['x'] * $scale, $element['y'] * $scale);
    }


    /**
     * Shape element
     */
    protected function addShapeElement($canvas, $element, $scale)
    {
        // (keep same logic)
    }


    protected function getFontPath($fontFamily)
    {
        return public_path("fonts/" . $fontFamily . ".ttf");
    }


    /**
     * Convert to PDF
     */
    protected function convertImageToPdf($imagePath, $pdfPath)
    {
        $pdf = Pdf::loadView('pdf.card', ['imagePath' => $imagePath]);
        $pdf->save($pdfPath);
    }
}
