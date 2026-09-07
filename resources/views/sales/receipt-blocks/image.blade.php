@php $imagePath = (string) ($block->config['image_path'] ?? ''); @endphp
@if ($imagePath !== '')
    <div class="rcpt-logo-img"><img src="{{ \Illuminate\Support\Facades\Storage::url($imagePath) }}" alt=""></div>
@endif
