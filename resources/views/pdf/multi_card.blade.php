<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;">
@foreach ($pages as $page)
    <div style="page-break-after: always;">
        <img src="{{ $page }}" style="width:100%; height:auto;">
    </div>
@endforeach
</body>
</html>
