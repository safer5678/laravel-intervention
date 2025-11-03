<?php

namespace App\Services;

use App\Models\CardOrder;
use Intervention\Image\Laravel\Facades\Image;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class CardGeneratorService


{

    
   public function generatePreview(CardOrder $cardOrder)
{
    $dpi = 150;
    $width = (int)($cardOrder->width_mm * $dpi / 25.4);
    $height = (int)($cardOrder->height_mm * $dpi / 25.4);
    $scale = $dpi / 25.4;

    // ✅ Create canvas for the preview (front page only)
    $canvas = Image::create($width, $height)->fill($cardOrder->background_color);

    // ✅ Insert background image if exists
    if (!empty($cardOrder->background_image)) {
        $bgPath = public_path($cardOrder->background_image);
        if (file_exists($bgPath)) {
            $canvas->place(Image::read($bgPath)->resize($width, $height), 'top-left', 0, 0);
        }
    }

    // ✅ Apply front elements ONLY
    if (!empty($cardOrder->card_data['front_elements'])) {
        foreach ($cardOrder->card_data['front_elements'] as $element) {
            $this->addElementToCanvas($canvas, $element, $scale, $dpi);
        }
    }

    // ✅ Save Preview PNG
    $filename = 'preview_' . $cardOrder->order_number . '.png';
    $savePath = storage_path('app/public/previews/' . $filename);

    if (!file_exists(dirname($savePath))) {
        mkdir(dirname($savePath), 0755, true);
    }

    $canvas->save($savePath, quality: 90);

    return '/storage/previews/' . $filename;
}


    public function generatePrintFile(CardOrder $cardOrder)
    {
        return $this->renderPages($cardOrder, $dpi = 300, $preview = false);
    }

    protected function renderPages($cardOrder, $dpi, $preview)
    {
        $width = (int)($cardOrder->width_mm * $dpi / 25.4);
        $height = (int)($cardOrder->height_mm * $dpi / 25.4);
        $scale = $dpi / 25.4;

        $pages = [
            'front_elements',
            'inside_left_elements',
            'inside_right_elements',
            'back_elements'
        ];
        

        $images = [];

        foreach ($pages as $pageKey) {
            $canvas = Image::create($width, $height)->fill($cardOrder->background_color);

            if (!empty($cardOrder->card_data[$pageKey])) {
                foreach ($cardOrder->card_data[$pageKey] as $element) {
                    $this->addElementToCanvas($canvas, $element, $scale, $dpi);
                }
            }

            $filename = "{$pageKey}_{$cardOrder->order_number}.jpg";
            $savePath = storage_path("app/public/print_files/$filename");

            if (!file_exists(dirname($savePath))) mkdir(dirname($savePath), 0755, true);

            $canvas->save($savePath, quality: 100);
            $images[] = $savePath;
        }

        // Generate Multi-Page PDF
        $pdf = Pdf::loadView('pdf.multi_card', ['pages' => $images]);
        $pdfPath = storage_path("app/public/print_files/print_{$cardOrder->order_number}.pdf");
        $pdf->save($pdfPath);

        return '/storage/print_files/print_' . $cardOrder->order_number . '.pdf';
    }

    protected function addElementToCanvas($canvas, $element, $scale, $dpi)
    {
        if ($element['type'] === 'text') {
            $this->addTextElement($canvas, $element, $scale, $dpi);
        } elseif ($element['type'] === 'image') {
            $this->addImageElement($canvas, $element, $scale);
        }
    }

   protected function addTextElement($canvas, $element, $scale, $dpi = 300)
{
    $frontendFontSize = $element['font_size'];
    $fontSize = (int)($frontendFontSize * ($dpi / 96));

    $x = (int)($element['x'] * $scale);
    $y = (int)($element['y'] * $scale);

    $fontPath = $this->getFontPath($element['font_family'] ?? 'Arial');

    // ✅ Define text box area (mm → px)
    $maxWidthMm = 120; // text area width in mm (adjust if needed)
    $maxWidthPx = (int)($maxWidthMm * $scale);

    // ✅ Let Intervention wrap automatically
    $canvas->text($element['text'], $x, $y, function($font) use ($fontSize, $fontPath, $element, $maxWidthPx) {
        if (file_exists($fontPath)) {
            $font->file($fontPath);
        }

        $font->size($fontSize);
        $font->color($element['color'] ?? '#000000');

        // ✅ Alignment works automatically with box max-width
        $font->align($element['align'] ?? 'center');
        $font->valign('top');

        // ✅ Set bounding box (THIS FIXES OVERFLOW!)
        $font->wrap($maxWidthPx);
    });
}



    protected function addImageElement($canvas, $element, $scale)
    {
        $imagePath = public_path($element['src']);
        if (!file_exists($imagePath)) return;

        $img = Image::read($imagePath);

        if (isset($element['width']) && isset($element['height'])) {
            $img->resize((int)($element['width'] * $scale), (int)($element['height'] * $scale));
        }

        $canvas->insert($img, 'top-left', (int)($element['x'] * $scale), (int)($element['y'] * $scale));
    }

    protected function getFontPath($fontFamily)
    {
        return public_path("fonts/{$fontFamily}.ttf");
    }

    protected function wrapText($text, $fontPath, $fontSize, $maxWidth)
{
    $words = explode(' ', $text);
    $wrapped = '';
    $line = '';

    foreach ($words as $word) {
        $testLine = $line . $word . ' ';
        $box = imagettfbbox($fontSize, 0, $fontPath, $testLine);
        $textWidth = abs($box[2] - $box[0]);

        if ($textWidth > $maxWidth) {
            $wrapped .= rtrim($line) . "\n";
            $line = $word . ' ';
        } else {
            $line = $testLine;
        }
    }

    return $wrapped . trim($line);
}

}
