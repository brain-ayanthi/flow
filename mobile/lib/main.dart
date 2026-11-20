import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

void main() => runApp(const PressFlowApp());

class PressFlowApp extends StatefulWidget {
  const PressFlowApp({super.key});

  @override
  State<PressFlowApp> createState() => _PressFlowAppState();
}

class _PressFlowAppState extends State<PressFlowApp> {
  String language = 'en';

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'PressFlow',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xff117765)),
        scaffoldBackgroundColor: const Color(0xfff5f8f6),
      ),
      supportedLocales: const [Locale('en'), Locale('si'), Locale('ta')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      home: PressFlowShell(
        language: language,
        onLanguageChanged: (value) => setState(() => language = value),
      ),
    );
  }
}

class PressFlowShell extends StatefulWidget {
  const PressFlowShell({super.key, required this.language, required this.onLanguageChanged});

  final String language;
  final ValueChanged<String> onLanguageChanged;

  @override
  State<PressFlowShell> createState() => _PressFlowShellState();
}

class _PressFlowShellState extends State<PressFlowShell> {
  int selectedIndex = 0;

  String tr(String key) => copy[widget.language]?[key] ?? copy['en']![key] ?? key;

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      DashboardView(language: widget.language),
      OrdersView(language: widget.language),
      ProductionView(language: widget.language),
      SimpleView(language: widget.language, title: tr('customers'), icon: Icons.people_outline),
      SimpleView(language: widget.language, title: tr('invoices'), icon: Icons.receipt_long_outlined),
    ];

    return Scaffold(
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
        titleSpacing: 18,
        title: Row(children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(color: const Color(0xff117765), borderRadius: BorderRadius.circular(10)),
            child: const Icon(Icons.print_rounded, color: Colors.white, size: 18),
          ),
          const SizedBox(width: 9),
          const Text('pressflow', style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: -0.4)),
        ]),
        actions: [
          DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              value: widget.language,
              borderRadius: BorderRadius.circular(12),
              items: const [
                DropdownMenuItem(value: 'en', child: Text('EN')),
                DropdownMenuItem(value: 'si', child: Text('සිංහල')),
                DropdownMenuItem(value: 'ta', child: Text('தமிழ்')),
              ],
              onChanged: (value) { if (value != null) widget.onLanguageChanged(value); },
            ),
          ),
          const SizedBox(width: 8),
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: CircleAvatar(
              radius: 16,
              backgroundColor: const Color(0xffe5eee8),
              child: const Text('RK', style: TextStyle(fontSize: 10, color: Color(0xff276b58), fontWeight: FontWeight.w800)),
            ),
          ),
        ],
      ),
      body: IndexedStack(index: selectedIndex, children: pages),
      floatingActionButton: selectedIndex < 2
          ? FloatingActionButton.extended(
              onPressed: _newOrder,
              backgroundColor: const Color(0xff117765),
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add),
              label: Text(tr('newOrder')),
            )
          : null,
      bottomNavigationBar: NavigationBar(
        selectedIndex: selectedIndex,
        onDestinationSelected: (value) => setState(() => selectedIndex = value),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.space_dashboard_outlined), selectedIcon: const Icon(Icons.space_dashboard), label: tr('overview')),
          NavigationDestination(icon: const Icon(Icons.receipt_long_outlined), selectedIcon: const Icon(Icons.receipt_long), label: tr('orders')),
          NavigationDestination(icon: const Icon(Icons.precision_manufacturing_outlined), selectedIcon: const Icon(Icons.precision_manufacturing), label: tr('production')),
          NavigationDestination(icon: const Icon(Icons.people_outline), selectedIcon: const Icon(Icons.people), label: tr('customers')),
          NavigationDestination(icon: const Icon(Icons.account_balance_wallet_outlined), selectedIcon: const Icon(Icons.account_balance_wallet), label: tr('invoices')),
        ],
      ),
    );
  }

  Future<void> _newOrder() async {
    final customerController = TextEditingController();
    final jobController = TextEditingController();
    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(tr('newOrder')),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: customerController, decoration: InputDecoration(labelText: tr('customerName'))),
          const SizedBox(height: 12),
          TextField(controller: jobController, decoration: InputDecoration(labelText: tr('job'))),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(tr('cancel'))),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(tr('createOrder'))),
        ],
      ),
    );
    customerController.dispose();
    jobController.dispose();
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tr('demoOnly'))));
    }
  }
}

class DashboardView extends StatelessWidget {
  const DashboardView({super.key, required this.language});
  final String language;
  String tr(String key) => copy[language]?[key] ?? copy['en']![key] ?? key;

  @override
  Widget build(BuildContext context) {
    return ListView(padding: const EdgeInsets.fromLTRB(16, 20, 16, 100), children: [
      Text(tr('goodMorning'), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.7)),
      const SizedBox(height: 5),
      Text(tr('welcome'), style: const TextStyle(color: Color(0xff788680), fontSize: 13)),
      const SizedBox(height: 18),
      Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          gradient: const LinearGradient(colors: [Color(0xff103f37), Color(0xff217b68)]),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(tr('heroTag'), style: const TextStyle(color: Color(0xffd5f16a), fontSize: 10, letterSpacing: 1.1, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          Text(tr('heroTitle'), style: const TextStyle(color: Colors.white, fontSize: 21, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          Text(tr('heroText'), style: const TextStyle(color: Colors.white70, fontSize: 12, height: 1.5)),
        ]),
      ),
      const SizedBox(height: 14),
      Wrap(spacing: 10, runSpacing: 10, children: [
        _MetricCard(label: tr('openOrders'), value: '128', detail: '↑ 12%', icon: Icons.receipt_long_outlined),
        _MetricCard(label: tr('inProduction'), value: '42', detail: '8 ${tr('dueToday')}', icon: Icons.precision_manufacturing_outlined),
        _MetricCard(label: tr('outstanding'), value: '486,200', detail: 'LKR · 6 invoices', icon: Icons.payments_outlined),
        _MetricCard(label: tr('onTime'), value: '96.4%', detail: '↑ 3.2%', icon: Icons.task_alt),
      ]),
      const SizedBox(height: 18),
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(tr('ordersOverview'), style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
        TextButton(onPressed: () {}, child: Text(tr('viewAll'))),
      ]),
      const SizedBox(height: 8),
      const OrderTile(number: 'PF-1042', customer: 'Cedar Café', job: 'Menu cards · Digital', due: 'Oct 11', total: 'LKR 42,500', status: 'Needs proof'),
      const SizedBox(height: 9),
      const OrderTile(number: 'PF-1041', customer: 'Dambulla Tours', job: 'Vehicle wrap · Flex', due: 'Oct 12', total: 'LKR 185,000', status: 'In production'),
      const SizedBox(height: 9),
      const OrderTile(number: 'PF-1040', customer: 'Aara Fashion', job: 'Hang tags · Offset', due: 'Oct 13', total: 'LKR 68,750', status: 'Finishing'),
      const SizedBox(height: 14),
      Card(child: Padding(padding: const EdgeInsets.all(16), child: Row(children: [
        const Icon(Icons.workspace_premium_outlined, color: Color(0xff117765)),
        const SizedBox(width: 11),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${tr('activePlan')}: Business', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
          const SizedBox(height: 4),
          Text('${tr('ordersUsage')}: 68 / 300 · ${tr('activeUntil')}', style: const TextStyle(color: Color(0xff788680), fontSize: 10)),
        ])),
        const Icon(Icons.chevron_right, color: Color(0xff87948c)),
      ]))),
      const SizedBox(height: 14),
      Text(tr('demoOnly'), style: const TextStyle(color: Color(0xff947848), fontSize: 10)),
    ]);
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value, required this.detail, required this.icon});
  final String label;
  final String value;
  final String detail;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final width = (MediaQuery.sizeOf(context).width - 42) / 2;
    return SizedBox(
      width: width.clamp(145.0, 360.0).toDouble(),
      child: Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Expanded(child: Text(label, style: const TextStyle(color: Color(0xff788680), fontSize: 10))), Icon(icon, color: const Color(0xff43826a), size: 17)]),
        const SizedBox(height: 10),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20, letterSpacing: -0.5)),
        const SizedBox(height: 5),
        Text(detail, style: const TextStyle(color: Color(0xff43826a), fontSize: 9, fontWeight: FontWeight.w600)),
      ]))),
    );
  }
}

class OrdersView extends StatelessWidget {
  const OrdersView({super.key, required this.language});
  final String language;
  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.fromLTRB(16, 20, 16, 100), children: [
    Text(copy[language]?['orders'] ?? 'Orders', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
    const SizedBox(height: 6),
    Text(copy[language]?['ordersSubtitle'] ?? 'Track customer jobs from brief to delivery.', style: const TextStyle(color: Color(0xff788680), fontSize: 12)),
    const SizedBox(height: 16),
    const OrderTile(number: 'PF-1042', customer: 'Cedar Café', job: 'Menu cards · Digital', due: 'Oct 11', total: 'LKR 42,500', status: 'Needs proof'),
    const SizedBox(height: 9),
    const OrderTile(number: 'PF-1041', customer: 'Dambulla Tours', job: 'Vehicle wrap · Flex', due: 'Oct 12', total: 'LKR 185,000', status: 'In production'),
    const SizedBox(height: 9),
    const OrderTile(number: 'PF-1040', customer: 'Aara Fashion', job: 'Hang tags · Offset', due: 'Oct 13', total: 'LKR 68,750', status: 'Finishing'),
    const SizedBox(height: 9),
    const OrderTile(number: 'PF-1039', customer: 'Green Leaf Market', job: 'Product labels · Digital', due: 'Oct 14', total: 'LKR 26,500', status: 'Ready'),
  ]);
}

class ProductionView extends StatelessWidget {
  const ProductionView({super.key, required this.language});
  final String language;
  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.fromLTRB(16, 20, 16, 100), children: [
    Text(copy[language]?['production'] ?? 'Production', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
    const SizedBox(height: 6),
    Text(copy[language]?['productionSubtitle'] ?? 'A quick view of jobs moving through the print floor.', style: const TextStyle(color: Color(0xff788680), fontSize: 12)),
    const SizedBox(height: 16),
    const OrderTile(number: 'PREPRESS · PF-1042', customer: 'Cedar Café', job: 'Design approval · Menu cards', due: '10:00', total: 'Waiting proof', status: 'Needs proof'),
    const SizedBox(height: 9),
    const OrderTile(number: 'PRINTING · PF-1041', customer: 'Dambulla Tours', job: 'Vehicle wrap · Flex', due: '11:30', total: 'LKR 185,000', status: 'In production'),
    const SizedBox(height: 9),
    const OrderTile(number: 'FINISHING · PF-1040', customer: 'Aara Fashion', job: 'Hang tags · Offset', due: '13:15', total: 'LKR 68,750', status: 'Finishing'),
    const SizedBox(height: 9),
    const OrderTile(number: 'READY · PF-1039', customer: 'Green Leaf Market', job: 'Product labels · Digital', due: '14:00', total: 'Ready to dispatch', status: 'Ready'),
  ]);
}

class SimpleView extends StatelessWidget {
  const SimpleView({super.key, required this.language, required this.title, required this.icon});
  final String language;
  final String title;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Center(child: Padding(padding: const EdgeInsets.all(28), child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, size: 42, color: const Color(0xff117765)),
    const SizedBox(height: 12),
    Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20)),
    const SizedBox(height: 8),
    Text(copy[language]?['nextPhase'] ?? 'This module is included in the Laravel API implementation plan.', textAlign: TextAlign.center, style: const TextStyle(color: Color(0xff788680), fontSize: 12)),
  ])));
}

class OrderTile extends StatelessWidget {
  const OrderTile({super.key, required this.number, required this.customer, required this.job, required this.due, required this.total, required this.status});
  final String number;
  final String customer;
  final String job;
  final String due;
  final String total;
  final String status;

  Color get statusColor => switch (status) {
    'Ready' => const Color(0xff247757),
    'In production' => const Color(0xff306e9a),
    'Finishing' => const Color(0xff7c5cab),
    _ => const Color(0xff8c691c),
  };

  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(13), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(children: [Expanded(child: Text(number, style: const TextStyle(color: Color(0xff117765), fontWeight: FontWeight.w800, fontSize: 10))), Text(due, style: const TextStyle(color: Color(0xff788680), fontSize: 9))]),
    const SizedBox(height: 7),
    Text(customer, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
    const SizedBox(height: 4),
    Text(job, style: const TextStyle(color: Color(0xff788680), fontSize: 10)),
    const SizedBox(height: 11),
    Row(children: [
      Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5), decoration: BoxDecoration(color: statusColor.withOpacity(.1), borderRadius: BorderRadius.circular(20)), child: Text(status, style: TextStyle(color: statusColor, fontSize: 9, fontWeight: FontWeight.w700))),
      const Spacer(),
      Text(total, style: const TextStyle(color: Color(0xff425149), fontSize: 9, fontWeight: FontWeight.w700)),
    ]),
  ])));
}

const Map<String, Map<String, String>> copy = {
  'en': {
    'overview': 'Overview', 'orders': 'Orders', 'production': 'Production', 'customers': 'Customers', 'invoices': 'Invoices',
    'goodMorning': 'Good morning, Ruwan', 'welcome': 'Here’s what’s moving across your print floor today.',
    'heroTag': 'THE PRINT SHOP WORKSPACE', 'heroTitle': 'Your print floor, in sync.',
    'heroText': 'Track every job from customer brief to finished print — without losing the details in between.',
    'openOrders': 'Open orders', 'inProduction': 'In production', 'outstanding': 'Outstanding', 'onTime': 'On-time completion',
    'dueToday': 'due today', 'ordersOverview': 'Recent orders', 'viewAll': 'View all', 'activePlan': 'Active plan',
    'ordersUsage': 'Orders this month', 'newOrder': 'New order', 'customerName': 'Customer name', 'job': 'Job / service',
    'createOrder': 'Create order', 'cancel': 'Cancel', 'demoOnly': 'Demo preview only — data is not saved to the live system.',
    'ordersSubtitle': 'Track customer jobs from brief to delivery.', 'productionSubtitle': 'A quick view of jobs moving through the print floor.',
    'nextPhase': 'This module is included in the Laravel API implementation plan.', 'trialing': '14-day trial',
    'activeUntil': 'Active until Nov 09, 2026', 'needsProof': 'Needs proof',
  },
  'si': {
    'overview': 'සාරාංශය', 'orders': 'ඇණවුම්', 'production': 'නිෂ්පාදනය', 'customers': 'ගනුදෙනුකරුවන්', 'invoices': 'ඉන්වොයිසි',
    'goodMorning': 'සුබ උදෑසනක්, Ruwan', 'welcome': 'අද මුද්‍රණ කටයුතු වල තත්ත්වය මෙතැනින් බලන්න.',
    'heroTag': 'මුද්‍රණාල වැඩ අවකාශය', 'heroTitle': 'ඔබගේ මුද්‍රණ වැඩ එකම තැනක.',
    'heroText': 'පාරිභෝගික ඉල්ලීමේ සිට අවසන් මුද්‍රණය දක්වා සියලු වැඩ කළමනාකරණය කරන්න.',
    'openOrders': 'විවෘත ඇණවුම්', 'inProduction': 'නිෂ්පාදනයේ', 'outstanding': 'අයවිය යුතු', 'onTime': 'නියමිත වේලාවට',
    'dueToday': 'අද භාරදිය යුතු', 'ordersOverview': 'මෑත ඇණවුම්', 'viewAll': 'සියල්ල බලන්න', 'activePlan': 'ක්‍රියාත්මක සැලසුම',
    'ordersUsage': 'මෙම මාසයේ ඇණවුම්', 'newOrder': 'නව ඇණවුම', 'customerName': 'පාරිභෝගික නම', 'job': 'වැඩ / සේවාව',
    'createOrder': 'ඇණවුම සාදන්න', 'cancel': 'අවලංගු කරන්න', 'demoOnly': 'නියැදි පෙරදසුනක් පමණි — දත්ත සැබෑ පද්ධතියට සුරැකෙන්නේ නැත.',
    'ordersSubtitle': 'පාරිභෝගික වැඩ ආරම්භයේ සිට භාරදීම දක්වා බලන්න.', 'productionSubtitle': 'මුද්‍රණ අංශයේ වැඩවල සාරාංශයක්.',
    'nextPhase': 'මෙම කොටස Laravel API අදියරේදී සකස් කෙරේ.', 'trialing': 'දින 14ක අත්හදා බැලීම',
    'activeUntil': '2026 නොවැම්බර් 09 දක්වා ක්‍රියාත්මකයි', 'needsProof': 'සාක්ෂි අනුමැතිය අවශ්‍ය',
  },
  'ta': {
    'overview': 'மேலோட்டம்', 'orders': 'ஆர்டர்கள்', 'production': 'உற்பத்தி', 'customers': 'வாடிக்கையாளர்கள்', 'invoices': 'விலைப்பட்டியல்',
    'goodMorning': 'காலை வணக்கம், Ruwan', 'welcome': 'இன்றைய அச்சகப் பணிகளின் நிலையைப் பாருங்கள்.',
    'heroTag': 'அச்சகப் பணியிடம்', 'heroTitle': 'உங்கள் அச்சுப் பணிகள் ஒரே இடத்தில்.',
    'heroText': 'வாடிக்கையாளர் கோரிக்கையிலிருந்து இறுதி அச்சு வரை ஒவ்வொரு வேலையையும் கண்காணிக்கவும்.',
    'openOrders': 'திறந்த ஆர்டர்கள்', 'inProduction': 'உற்பத்தியில்', 'outstanding': 'பெற வேண்டியது', 'onTime': 'நேரத்தில் முடித்தவை',
    'dueToday': 'இன்று ஒப்படைக்கவும்', 'ordersOverview': 'சமீபத்திய ஆர்டர்கள்', 'viewAll': 'அனைத்தையும் காண்க', 'activePlan': 'செயலில் உள்ள திட்டம்',
    'ordersUsage': 'இந்த மாத ஆர்டர்கள்', 'newOrder': 'புதிய ஆர்டர்', 'customerName': 'வாடிக்கையாளர் பெயர்', 'job': 'வேலை / சேவை',
    'createOrder': 'ஆர்டர் உருவாக்கு', 'cancel': 'ரத்து செய்', 'demoOnly': 'முன்மாதிரி மட்டும் — தரவு உண்மையான அமைப்பில் சேமிக்கப்படவில்லை.',
    'ordersSubtitle': 'கோரிக்கையிலிருந்து விநியோகம் வரை வாடிக்கையாளர் வேலைகளைப் பின்தொடரவும்.', 'productionSubtitle': 'அச்சக உற்பத்திப் பணிகளின் விரைவு காட்சி.',
    'nextPhase': 'இந்த பகுதி Laravel API கட்டத்தில் உருவாக்கப்படும்.', 'trialing': '14 நாள் சோதனை',
    'activeUntil': '2026 நவம்பர் 09 வரை செயலில்', 'needsProof': 'மாதிரி ஒப்புதல் தேவை',
  },
};
