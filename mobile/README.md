# Flutter client starter

This folder is a lightweight Flutter Android/iOS starting point with a responsive dashboard shell, sample orders and a Sinhala/English/Tamil selector. It is not connected to a live API and has not been compiled in this sandbox (Flutter is not installed).

## Run locally

1. Install the Flutter SDK, then run `flutter pub get` from this directory.
2. After deploying the Laravel API and configuring Sanctum, provide its public HTTPS base URL:

```bash
flutter run --dart-define=API_BASE_URL=https://api.your-domain.lk/api/v1
```

The configuration constant is in `lib/core/api_config.dart`. Add secure token storage, API client/error handling, auth screens, and generated localization resources during the integration phase. Do not place PayHere merchant secrets in the mobile app.
