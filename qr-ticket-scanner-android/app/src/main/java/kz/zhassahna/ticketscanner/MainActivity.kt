package kz.zhassahna.ticketscanner

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import kotlinx.coroutines.launch
import java.util.UUID
import java.util.concurrent.Executors

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                Surface(modifier = Modifier.fillMaxSize()) {
                    TicketScannerApp()
                }
            }
        }
    }
}

@Composable
private fun TicketScannerApp() {
    val context = LocalContext.current
    val api = remember { TicketApi(BuildConfig.API_BASE_URL) }
    val deviceUid = remember {
        context.getSharedPreferences("scanner", Context.MODE_PRIVATE)
            .getString("device_uid", null)
            ?: UUID.randomUUID().toString().also {
                context.getSharedPreferences("scanner", Context.MODE_PRIVATE)
                    .edit().putString("device_uid", it).apply()
            }
    }
    var loggedIn by remember { mutableStateOf(false) }

    if (loggedIn) {
        ScannerScreen(api = api, deviceUid = deviceUid)
    } else {
        LoginScreen(
            api = api,
            onLogin = { loggedIn = true }
        )
    }
}

@Composable
private fun LoginScreen(api: TicketApi, onLogin: () -> Unit) {
    var username by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var error by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    Column(
        modifier = Modifier.fillMaxSize().padding(24.dp),
        verticalArrangement = Arrangement.Center
    ) {
        Text("Сканер билетов", style = MaterialTheme.typography.headlineMedium)
        Spacer(Modifier.height(8.dp))
        Text("Войдите под учётной записью сотрудника.")
        Spacer(Modifier.height(24.dp))
        OutlinedTextField(
            value = username,
            onValueChange = { username = it },
            label = { Text("Имя пользователя") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )
        Spacer(Modifier.height(12.dp))
        OutlinedTextField(
            value = password,
            onValueChange = { password = it },
            label = { Text("Пароль") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )
        if (error.isNotBlank()) {
            Spacer(Modifier.height(12.dp))
            Text(error, color = MaterialTheme.colorScheme.error)
        }
        Spacer(Modifier.height(18.dp))
        Button(
            onClick = {
                loading = true
                error = ""
                scope.launch {
                    api.login(username.trim(), password)
                        .onSuccess { onLogin() }
                        .onFailure { error = it.message ?: "Ошибка входа." }
                    loading = false
                }
            },
            enabled = !loading && username.isNotBlank() && password.isNotBlank(),
            modifier = Modifier.fillMaxWidth()
        ) {
            if (loading) {
                CircularProgressIndicator(Modifier.size(20.dp))
            } else {
                Text("Войти")
            }
        }
    }
}

@Composable
private fun ScannerScreen(api: TicketApi, deviceUid: String) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var hasCamera by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) ==
                PackageManager.PERMISSION_GRANTED
        )
    }
    var result by remember { mutableStateOf<ScanResult?>(null) }
    var manualCode by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var lastSubmittedCode by remember { mutableStateOf("") }
    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { hasCamera = it }

    LaunchedEffect(Unit) {
        if (!hasCamera) permissionLauncher.launch(Manifest.permission.CAMERA)
    }

    fun submit(code: String) {
        val normalizedCode = code.trim()
        if (loading || normalizedCode.isBlank() || normalizedCode == lastSubmittedCode) return
        lastSubmittedCode = normalizedCode
        loading = true
        scope.launch {
            api.checkIn(normalizedCode, deviceUid)
                .onSuccess { result = it }
                .onFailure {
                    result = ScanResult(false, message = it.message ?: "Ошибка связи с сервером.")
                }
            loading = false
        }
    }

    Column(
        modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp)
    ) {
        Text("Сканер билетов", style = MaterialTheme.typography.headlineMedium)
        Spacer(Modifier.height(6.dp))
        Text("Наведите камеру на QR-код оплаченного билета.")
        Spacer(Modifier.height(16.dp))

        if (hasCamera) {
            CameraPreview(
                modifier = Modifier.fillMaxWidth().height(330.dp),
                onCode = ::submit
            )
        } else {
            Text("Доступ к камере не предоставлен.", color = MaterialTheme.colorScheme.error)
            OutlinedButton(onClick = {
                permissionLauncher.launch(Manifest.permission.CAMERA)
            }) {
                Text("Разрешить камеру")
            }
        }

        Spacer(Modifier.height(16.dp))
        OutlinedTextField(
            value = manualCode,
            onValueChange = { manualCode = it },
            label = { Text("UID билета") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )
        Spacer(Modifier.height(8.dp))
        Button(
            onClick = { submit(manualCode) },
            enabled = !loading && manualCode.isNotBlank(),
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Проверить UID")
        }

        result?.let {
            Spacer(Modifier.height(16.dp))
            ScanResultCard(it)
            Spacer(Modifier.height(8.dp))
            OutlinedButton(
                onClick = {
                    result = null
                    lastSubmittedCode = ""
                },
                modifier = Modifier.fillMaxWidth()
            ) {
                Text("Сканировать следующий билет")
            }
        }
    }
}

@Composable
private fun ScanResultCard(result: ScanResult) {
    val color = when {
        result.accepted -> MaterialTheme.colorScheme.primary
        result.duplicate -> MaterialTheme.colorScheme.tertiary
        else -> MaterialTheme.colorScheme.error
    }
    Card(modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp)) {
            Text(
                when {
                    result.accepted -> "Билет принят"
                    result.duplicate -> "Билет уже использован"
                    else -> "Билет не принят"
                },
                style = MaterialTheme.typography.titleLarge,
                color = color
            )
            Spacer(Modifier.height(8.dp))
            Text(result.message)
            listOf(
                "UID" to result.details.uid,
                "Событие" to result.details.eventTitle,
                "Сеанс" to result.details.sessionStart,
                "Место" to result.details.seat,
                "Клиент" to result.details.customer
            ).filter { it.second.isNotBlank() }.forEach { (label, value) ->
                Spacer(Modifier.height(6.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("$label:", color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text(value)
                }
            }
        }
    }
}

@Composable
private fun CameraPreview(modifier: Modifier, onCode: (String) -> Unit) {
    val context = LocalContext.current
    val lifecycleOwner = androidx.lifecycle.compose.LocalLifecycleOwner.current

    AndroidView(
        modifier = modifier,
        factory = {
            val previewView = PreviewView(context)
            val cameraProviderFuture = ProcessCameraProvider.getInstance(context)
            val executor = Executors.newSingleThreadExecutor()
            val scanner = BarcodeScanning.getClient(
                BarcodeScannerOptions.Builder()
                    .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
                    .build()
            )

            cameraProviderFuture.addListener({
                val provider = cameraProviderFuture.get()
                val preview = Preview.Builder().build().also {
                    it.surfaceProvider = previewView.surfaceProvider
                }
                val analysis = ImageAnalysis.Builder()
                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                    .build()
                analysis.setAnalyzer(executor) { imageProxy ->
                    val image = imageProxy.image
                    if (image == null) {
                        imageProxy.close()
                        return@setAnalyzer
                    }
                    scanner.process(
                        InputImage.fromMediaImage(image, imageProxy.imageInfo.rotationDegrees)
                    ).addOnSuccessListener { barcodes ->
                        barcodes.firstOrNull()?.rawValue?.let(onCode)
                    }.addOnCompleteListener {
                        imageProxy.close()
                    }
                }
                provider.unbindAll()
                provider.bindToLifecycle(
                    lifecycleOwner,
                    CameraSelector.DEFAULT_BACK_CAMERA,
                    preview,
                    analysis
                )
            }, ContextCompat.getMainExecutor(context))
            previewView
        }
    )
}
