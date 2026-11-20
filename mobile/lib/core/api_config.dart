/// Build with:
/// flutter run --dart-define=API_BASE_URL=https://api.your-domain.lk/api/v1
///
/// Keep real API keys/secrets on the Laravel server; never ship gateway secrets
/// in a mobile app. This URL is configuration only until the API client is wired.
const String apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://api.example.lk/api/v1',
);
