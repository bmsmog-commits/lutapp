@auth
    <details style="margin-top:8px;">
        <summary class="muted" style="cursor:pointer;">Report</summary>
        <form method="post" action="{{ route('report.store') }}" style="margin-top:8px;max-width:360px;">
            @csrf
            <input type="hidden" name="reportable_type" value="{{ $reportableType }}">
            <input type="hidden" name="reportable_id" value="{{ $reportableId }}">
            <div class="field">
                <label for="reason">Reason</label>
                <select class="input" id="reason" name="reason" required>
                    @foreach (\App\Models\Report::REASONS as $reason)
                        <option value="{{ $reason }}">{{ ucfirst(str_replace('_', ' ', $reason)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="description">Details (optional)</label>
                <textarea id="description" name="description" maxlength="3000"></textarea>
            </div>
            <button class="btn" type="submit" style="margin-top:8px;">Submit report</button>
        </form>
    </details>
@endauth
