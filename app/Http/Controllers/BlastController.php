<?php

namespace App\Http\Controllers;

use App\Jobs\SendBlastEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Validator;

class BlastController extends Controller
{
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'subject' => ['required', 'string', 'max:200'],
            'html' => ['required_without:html_file', 'string'],
            'html_file' => ['required_without:html', 'file'],
            'from_email' => ['nullable', 'email'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'list' => ['nullable', 'file'],
            'recipients' => ['nullable', 'array'],
            'recipients.*.email' => ['required_with:recipients', 'email'],
            'recipients.*.name' => ['nullable', 'string', 'max:120'],
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $html = $request->string('html', '');
        if (!$html && $request->file('html_file')) {
            $html = file_get_contents($request->file('html_file')->getRealPath());
        }

        $rows = [];
        if ($request->file('list')) {
            $rows = $this->parseCsv($request->file('list')->getRealPath());
        } elseif ($request->has('recipients')) {
            $rows = collect($request->input('recipients', []))
                ->map(fn($r) => ['email' => $r['email'], 'name' => $r['name'] ?? null])
                ->all();
        }

        if (empty($rows)) {
            return response()->json(['queued' => 0, 'message' => 'No recipients'], 200);
        }

        $subject = $request->string('subject');
        $fromEmail = $request->string('from_email', '');
        $fromName = $request->string('from_name', '');

        $batch = Bus::batch([])->dispatch();
        $count = 0;

        foreach ($rows as $r) {
            if (empty($r['email'])) continue;
            $payload = [
                'email' => trim($r['email']),
                'name' => $r['name'] ?? null,
                'subject' => $subject,
                'html' => $html,
                'from_email' => $fromEmail ?: null,
                'from_name' => $fromName ?: null,
            ];
            $batch->add(new SendBlastEmail($payload));
            $count++;
        }

        return response()->json(['queued' => $count, 'batch_id' => $batch->id], 202);
    }

    private function parseCsv(string $path): array
    {
        $out = [];
        if (!is_readable($path)) return $out;
        $h = fopen($path, 'r');
        if (!$h) return $out;

        $headers = [];
        if (($row = fgetcsv($h, 0, ',')) !== false) {
            $headers = array_map(fn($x) => strtolower(trim($x)), $row);
        }

        while (($row = fgetcsv($h, 0, ',')) !== false) {
            if (count($row) === 1 && filter_var($row[0], FILTER_VALIDATE_EMAIL)) {
                $out[] = ['email' => trim($row[0])];
                continue;
            }
            $rec = [];
            foreach ($row as $i => $val) {
                $key = $headers[$i] ?? "col{$i}";
                $rec[$key] = trim((string)$val);
            }
            $email = $rec['email'] ?? $rec['e-mail'] ?? null;
            if (!$email) continue;
            $name = $rec['name'] ?? trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? ''));
            $out[] = ['email' => $email, 'name' => $name ?: null];
        }
        fclose($h);
        return $out;
    }
}
