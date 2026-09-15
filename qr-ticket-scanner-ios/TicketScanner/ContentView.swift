import SwiftUI

@MainActor
final class ScannerModel: ObservableObject {
    @Published var isLoggedIn = false
    @Published var isLoading = false
    @Published var errorMessage = ""
    @Published var result: CheckinResponse?

    let api = TicketAPI()
    let deviceUid = UIDevice.current.identifierForVendor?.uuidString ?? UUID().uuidString

    func login(username: String, password: String) {
        isLoading = true
        errorMessage = ""
        Task {
            do {
                try await api.login(username: username, password: password)
                isLoggedIn = true
            } catch {
                errorMessage = error.localizedDescription
            }
            isLoading = false
        }
    }

    func checkIn(code: String) {
        guard !isLoading, !code.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            return
        }
        isLoading = true
        Task {
            do {
                result = try await api.checkIn(code: code, deviceUid: deviceUid)
            } catch {
                errorMessage = error.localizedDescription
            }
            isLoading = false
        }
    }
}

struct ContentView: View {
    @StateObject private var model = ScannerModel()
    @State private var username = ""
    @State private var password = ""
    @State private var manualCode = ""
    @State private var lastCode = ""

    var body: some View {
        Group {
            if model.isLoggedIn {
                scannerView
            } else {
                loginView
            }
        }
        .padding()
    }

    private var loginView: some View {
        VStack(spacing: 16) {
            Spacer()
            Text("Сканер билетов")
                .font(.largeTitle.bold())
            Text("Войдите под учётной записью сотрудника.")
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            TextField("Имя пользователя", text: $username)
                .textInputAutocapitalization(.never)
                .textFieldStyle(.roundedBorder)
            SecureField("Пароль", text: $password)
                .textFieldStyle(.roundedBorder)
            if !model.errorMessage.isEmpty {
                Text(model.errorMessage)
                    .foregroundStyle(.red)
            }
            Button("Войти") {
                model.login(username: username, password: password)
            }
            .buttonStyle(.borderedProminent)
            .disabled(model.isLoading || username.isEmpty || password.isEmpty)
            Spacer()
        }
    }

    private var scannerView: some View {
        ScrollView {
            VStack(spacing: 14) {
                Text("Сканер билетов")
                    .font(.largeTitle.bold())
                    .frame(maxWidth: .infinity, alignment: .leading)
                Text("Наведите камеру на QR-код оплаченного билета.")
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .foregroundStyle(.secondary)

                QRScannerView { code in
                    let normalized = code.trimmingCharacters(in: .whitespacesAndNewlines)
                    guard normalized != lastCode else { return }
                    lastCode = normalized
                    model.checkIn(code: normalized)
                }
                .frame(height: 330)
                .clipShape(RoundedRectangle(cornerRadius: 12))

                TextField("UID билета", text: $manualCode)
                    .textFieldStyle(.roundedBorder)
                    .textInputAutocapitalization(.never)
                Button("Проверить UID") {
                    lastCode = manualCode
                    model.checkIn(code: manualCode)
                }
                .buttonStyle(.borderedProminent)
                .disabled(model.isLoading || manualCode.isEmpty)

                if let result = model.result {
                    resultCard(result)
                    Button("Сканировать следующий билет") {
                        lastCode = ""
                        model.result = nil
                    }
                    .buttonStyle(.bordered)
                }
            }
        }
    }

    private func resultCard(_ result: CheckinResponse) -> some View {
        let title = result.success == true
            ? "Билет принят"
            : (result.duplicate == true ? "Билет уже использован" : "Билет не принят")
        return VStack(alignment: .leading, spacing: 8) {
            Text(title)
                .font(.title2.bold())
            Text(result.message ?? "Проверка завершена.")
            if let data = result.data {
                detail("UID", data.ticketUid)
                detail("Событие", data.eventTitle)
                detail("Сеанс", data.sessionStart)
                detail("Место", data.seatIdentifier)
                detail("Клиент", data.customerName)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding()
        .background(Color(.secondarySystemBackground))
        .clipShape(RoundedRectangle(cornerRadius: 12))
    }

    @ViewBuilder
    private func detail(_ label: String, _ value: String?) -> some View {
        if let value, !value.isEmpty {
            Text("\(label): \(value)")
        }
    }
}
