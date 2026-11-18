# Prueba rapida de la API de SeguPYME. Requiere que "php artisan serve" este corriendo en otra terminal.
# Uso (en la carpeta SeguPYME_Laravel):  powershell -ExecutionPolicy Bypass -File .\probar-api.ps1

$base = "http://localhost:8000/api"
$script:cab = @{}

function Llamar($metodo, $ruta, $cuerpo = $null) {
    $p = @{ Method = $metodo; Uri = "$base$ruta"; ContentType = "application/json"; Headers = $script:cab }
    if ($cuerpo) { $p.Body = $cuerpo }
    try {
        $r = Invoke-RestMethod @p
        return @{ ok = $true; datos = $r }
    } catch {
        $texto = $_.Exception.Message
        if ($_.Exception.Response) {
            $flujo = $_.Exception.Response.GetResponseStream()
            $texto = (New-Object System.IO.StreamReader($flujo)).ReadToEnd()
        }
        try { $texto = ($texto | ConvertFrom-Json).message } catch { }
        return @{ ok = $false; datos = $texto }
    }
}

function Mostrar($titulo, $res, $esperaRechazo) {
    $bien = ($res.ok -ne $esperaRechazo)
    $marca = if ($bien) { "[OK]   " } else { "[FALLA]" }
    Write-Host ""
    Write-Host "$marca $titulo" -ForegroundColor $(if ($bien) { "Green" } else { "Red" })
    if ($res.ok) { Write-Host ("        " + ($res.datos | ConvertTo-Json -Compress -Depth 4)) }
    else { Write-Host ("        Respuesta: " + $res.datos) }
}

# 1. Login
$login = Llamar "POST" "/login" '{"correo":"ana@textilesandina-prueba.co","contrasena":"Clave*2026"}'
Mostrar "Login correcto" $login $false
if (-not $login.ok) { Write-Host "No se pudo iniciar sesion. Revisa el servidor y la contrasena." -ForegroundColor Red; exit }
$script:cab = @{ Authorization = "Bearer " + $login.datos.token }

$mala = Llamar "POST" "/login" '{"correo":"ana@textilesandina-prueba.co","contrasena":"incorrecta"}'
Mostrar "Login con contrasena incorrecta se rechaza" $mala $true

# 2. Lectura
$lista = Llamar "GET" "/novedades"
Mostrar ("Lista de novedades (total antes: " + $lista.datos.total + ")") $lista $false

# 3. Reglas de fecha
$futura = (Get-Date).AddDays(5).ToString("yyyy-MM-dd")
$r1 = Llamar "POST" "/novedades" ('{"concepto":"HORA_EXTRA_DIURNA","fecha_inicio":"' + $futura + '","horas":2}')
Mostrar "Hora extra con fecha futura se rechaza" $r1 $true

$r2 = Llamar "POST" "/novedades" '{"concepto":"RECARGO_DOMINICAL_FESTIVO_DIURNO","fecha_inicio":"2026-11-11","horas":8}'
Mostrar "Recargo dominical en un miercoles se rechaza" $r2 $true

$r3 = Llamar "POST" "/novedades" '{"concepto":"RECARGO_DOMINICAL_FESTIVO_DIURNO","fecha_inicio":"2026-11-15","horas":8}'
Mostrar "Recargo dominical en domingo (15 nov) se acepta" $r3 $false

$hoy = (Get-Date).ToString("yyyy-MM-dd")
$r4 = Llamar "POST" "/novedades" ('{"concepto":"HORA_EXTRA_DIURNA","fecha_inicio":"' + $hoy + '","horas":2}')
Mostrar "Hora extra de hoy se acepta" $r4 $false

# 4. Permisos
$emp = Llamar "GET" "/empleados"
Mostrar "El Empleado no puede listar personas (403)" $emp $true

$lista2 = Llamar "GET" "/novedades"
Write-Host ""
Write-Host ("Total de novedades ahora: " + $lista2.datos.total + " (deben ser 2 mas que antes si todo se acepto)")
