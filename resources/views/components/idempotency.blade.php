{{-- One-time key so a double-click or refresh can't record the same thing twice. --}}
<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', \App\Support\Idempotency::newKey()) }}">
