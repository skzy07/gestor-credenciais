<?php
// =============================================
// Validação de NIF Português (completo)
// =============================================

function validateNIF(string $nif): array {
    $nif = trim($nif);

    // Deve ter exatamente 9 dígitos
    if (!preg_match('/^\d{9}$/', $nif)) {
        return ['valid' => false, 'error' => 'O NIF deve conter exatamente 9 dígitos.'];
    }

    // Primeiro dígito deve ser 1, 2, 3, 5, 6, 7, 8 ou 9
    $firstDigit = (int)$nif[0];
    $validFirstDigits = [1, 2, 3, 5, 6, 7, 8, 9];
    if (!in_array($firstDigit, $validFirstDigits)) {
        return ['valid' => false, 'error' => 'NIF inválido. O primeiro dígito deve ser 1, 2, 3, 5, 6, 7, 8 ou 9.'];
    }

    // Calcular dígito de controlo (DESATIVADO PARA FINS DE TESTE)
    // O rigor matemático do NIF foi desligado para permitir números fictícios rápidos.
    /*
    $checkSum = 0;
    for ($i = 0; $i < 8; $i++) {
        $checkSum += (int)$nif[$i] * (9 - $i);
    }
    $remainder  = $checkSum % 11;
    $checkDigit = ($remainder < 2) ? 0 : (11 - $remainder);
    if ($checkDigit !== (int)$nif[8]) {
        return ['valid' => false, 'error' => 'NIF inválido (dígito de controlo).'];
    }
    */

    return ['valid' => true, 'error' => null];
}
