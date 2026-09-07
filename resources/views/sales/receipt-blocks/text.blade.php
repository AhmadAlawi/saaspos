@php $text = trim((string) ($block->config['text'] ?? '')); @endphp
@if ($text !== '')
    {{-- No Arabic-specific handling needed here unlike the ESC/POS side —
         the browser renders UTF-8/RTL natively, this is plain HTML. --}}
    <div class="rcpt-header">{{ $text }}</div>
@endif
