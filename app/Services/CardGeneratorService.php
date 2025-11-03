<?php

namespace App\Services;

use App\Models\CardOrder;
use Intervention\Image\Laravel\Facades\Image;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Css\Color;

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

        $canvas = Image::create($width, $height)->fill($cardOrder->background_color);

        if ($cardOrder->background_image) {
            $bgPath = public_path($cardOrder->background_image);
            if (file_exists($bgPath)) {
                $canvas->place(Image::read($bgPath)->resize($width, $height), 'top-left', 0, 0);
            }
        }

        foreach ($cardOrder->card_data['elements'] as $element) {
            $this->addElementToCanvas($canvas, $element, $dpi);
        }
      

        $filename = 'preview_' . $cardOrder->order_number . '.png';
        $savePath = storage_path('app/public/previews/' . $filename);

        if (!file_exists(dirname($savePath))) mkdir(dirname($savePath), 0755, true);

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
                $canvas->place(Image::read($bgPath)->resize($width, $height), 'top-left', 0, 0);
            }
        }

        foreach ($cardOrder->card_data['elements'] as $element) {
            $this->addElementToCanvas($canvas, $element, $dpi);
        }

        $jpegPath = storage_path('app/public/print_files/print_' . $cardOrder->order_number . '.jpg');

        if (!file_exists(dirname($jpegPath))) mkdir(dirname($jpegPath), 0755, true);

        $canvas->save($jpegPath, quality: 100);

        $pdfPath = storage_path('app/public/print_files/print_' . $cardOrder->order_number . '.pdf');
        $this->convertImageToPdf($jpegPath, $pdfPath);

        return '/storage/print_files/print_' . $cardOrder->order_number . '.pdf';
    }


    /**
     * Add element to canvas
     */
    protected function addElementToCanvas($canvas, $element, $dpi)
    {
        $scale = $dpi / 25.4; // mm → px conversion

        if ($element['type'] === 'text') {
            $this->addTextElement($canvas, $element, $scale, $dpi);
        } elseif ($element['type'] === 'image') {
            $this->addImageElement($canvas, $element, $scale);
        }
    }


    /**
     * Text element
     */
    protected function addTextElement($canvas, $element, $scale, $dpi)
    {
        $frontendFontSize = $element['font_size'];
        $fontSize = (int)($frontendFontSize * ($dpi / 96)); // scale correctly

        $x = (int)($element['x'] * $scale);
        $y = (int)($element['y'] * $scale);

        $fontPath = $this->getFontPath($element['font_family'] ?? 'Arial');

        $canvas->text($element['text'], $x, $y, function ($font) use ($fontPath, $fontSize, $element) {
            if (file_exists($fontPath)) $font->file($fontPath);

            $font->size($fontSize);
            $font->color($element['color'] ?? '#000');
            $font->align($element['align'] ?? 'left');
            $font->valign('center');

            if (!empty($element['rotation'])) {
                $font->angle(-$element['rotation']);
            }
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

        if (isset($element['width'], $element['height'])) {
            $img->resize(
                (int)($element['width'] * $scale),
                (int)($element['height'] * $scale)
            );
        }

        if (!empty($element['rotation'])) {
            $img->rotate(-$element['rotation']);
        }

        $canvas->insert($img, 'top-left', (int)($element['x'] * $scale), (int)($element['y'] * $scale));
    }


    protected function getFontPath($fontFamily)
    {
        return public_path("fonts/{$fontFamily}.ttf");
    }


    protected function convertImageToPdf($imagePath, $pdfPath)
    {
        $pdf = Pdf::loadView('pdf.card', ['imagePath' => $imagePath]);
        $pdf->save($pdfPath);
    }
}
