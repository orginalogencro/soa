<div align="center">

```
 ██████╗  ██████╗      ███████╗███╗   ██╗ ██████╗██████╗  ██████╗ 
██╔═══██╗██╔════╝      ██╔════╝████╗  ██║██╔════╝██╔══██╗██╔═══██╗
██║   ██║██║  ███╗     █████╗  ██╔██╗ ██║██║     ██████╔╝██║   ██║
██║   ██║██║   ██║     ██╔══╝  ██║╚██╗██║██║     ██╔══██╗██║   ██║
╚██████╔╝╚██████╔╝     ███████╗██║ ╚████║╚██████╗██║  ██║╚██████╔╝
 ╚═════╝  ╚═════╝      ╚══════╝╚═╝  ╚═══╝ ╚═════╝╚═╝  ╚═╝ ╚═════╝
```

**IP Grabber v2 — by OG ENCRO**  
`risk scoring` · `VPN/Tor/Proxy detection` · `Telegram alerts` · `Gemini AI ready`

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-Bot%20API-26A5E4?style=flat-square&logo=telegram&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)
![Status](https://img.shields.io/badge/status-active-success?style=flat-square)

</div>

---

## ✨ Features

| Feature | Opis |
|---|---|
| 🔍 **IP Geolocation** | Kraj, miasto, region, ISP, ASN, lat/lng |
| 🛡 **VPN / Proxy / Tor** | Automatyczne wykrywanie przez geo.ipify.org |
| 🤖 **Bot UA detection** | python-requests, curl, headless chrome, etc. |
| ⚡ **Burst / Rate limit** | Wykrywanie automatycznych skanów z jednego IP |
| 🎯 **Risk Score 0–100** | Automatyczna ocena zagrożenia każdego wejścia |
| 🏷 **Tags** | `vpn`, `tor`, `proxy`, `datacenter`, `bot_ua`, `burst_activity` |
| 📲 **Telegram alerts** | Tekst + natywna lokalizacja (pinezka) + plik JSON |
| 📊 **Daily summary** | Dzienny raport z załącznikiem grabs.json |
| 🖥 **viewer.html** | Lokalny panel z filtrowaniem (bez serwera) |
| 🚫 **Blocklist export** | Format TXT / JSON / Nginx — do fail2ban i firewalli |
| 🧬 **Session fingerprint** | SHA256 z IP+UA+lang+referer |
| 🌍 **Traffic source** | telegram / facebook / instagram / direct / other |
| 📋 **Gemini AI ready** | `grabs.json` gotowy do wrzucenia do Gemini AI Studio |

---

## 📁 Struktura

```
ipgrabber/
├── pixel.php              ← Główny pixel tracker
├── viewer.html            ← Panel lokalny (open in browser)
├── blocklist_export.php   ← Eksport blokad IP
├── logs/
│   ├── grabs.json         ← Pełna tablica (Gemini AI Studio)
│   ├── grabs.jsonl        ← Streaming log
│   ├── grabs.txt          ← Czytelny log
│   └── ratelimit/         ← Pliki rate limit per IP
└── README.md
```

---

## ⚙️ Konfiguracja

Otwórz `pixel.php` i uzupełnij sekcję `CONFIG`:

```php
$TELEGRAM_BOT_TOKEN  = 'TWÓJ_TOKEN';       // od @BotFather
$TELEGRAM_CHAT_ID    = 'TWÓJ_CHAT_ID';     // twoje ID lub ID grupy
$IPIFY_API_KEY       = 'TWÓJ_KLUCZ';       // geo.ipify.org (free tier: 1000 req/mies)
```

### Skąd wziąć Chat ID?

1. Wyślij `/start` do swojego bota
2. Otwórz: `https://api.telegram.org/botTWÓJ_TOKEN/getUpdates`
3. Znajdź `"chat":{"id": XXXXXXX}` — to jest Twój chat ID

---

## 🚀 Deploy na hosting

```bash
# 1. Wgraj pliki na hosting (np. przez FTP / SSH)
scp pixel.php viewer.html blocklist_export.php user@host:/public_html/

# 2. Stwórz folder logs z prawami zapisu
ssh user@host "mkdir -p /public_html/logs && chmod 755 /public_html/logs"

# 3. Gotowe! Pixel URL:
# https://twojdomain.com/pixel.php
```

---

## 🖼 Użycie jako tracker pixel

```html
<!-- W HTML strony / wiadomości -->
<img src="https://twojdomain.com/pixel.php" width="1" height="1" style="display:none"/>
```

---

## 📊 Format danych (grabs.json)

Każdy wpis zawiera:

```json
{
  "id": "grab_6820a1b2c3d4e",
  "timestamp": "2026-05-04 02:00:00",
  "ip": "83.238.45.112",
  "hostname": "broadband.play.pl",
  "city": "Warszawa",
  "region": "Mazowieckie",
  "country": "PL",
  "lat": 52.2297,
  "lng": 21.0122,
  "isp": "Play",
  "asn": "AS39603",
  "tags": ["vpn"],
  "risk_score": 25,
  "risk_label": "🟡 ŚREDNI",
  "suspicion_reason": "VPN",
  "burst_activity": false,
  "traffic_source": "telegram",
  "is_bot": false,
  "user_agent": "Mozilla/5.0 ...",
  "sec_ch_ua": "\"Chromium\";v=\"124\"",
  "sec_fetch_site": "none",
  "sec_fetch_mode": "navigate",
  "dnt": "1",
  "referer": "Direct",
  "accept_language": "pl-PL,pl;q=0.9",
  "session_hash": "a3f9b2...",
  "map_link": "https://www.google.com/maps/search/?api=1&query=52.2297,21.0122",
  "raw_ipify": { "..." : "..." }
}
```

---

## 🚫 Blocklist Export

```bash
# JSON
https://twojdomain.com/blocklist_export.php?key=KLUCZ&format=json

# Jeden IP per linia (fail2ban / UFW)
https://twojdomain.com/blocklist_export.php?key=KLUCZ&format=txt

# Nginx deny rules
https://twojdomain.com/blocklist_export.php?key=KLUCZ&format=nginx
```

---

## 🤖 Prompt Gemini AI Studio

Wrzuć `logs/grabs.json` do [Gemini AI Studio](https://aistudio.google.com) i użyj:

```
Jesteś ekspertem od analizy logów bezpieczeństwa.

Oto dane z IP grabbera (plik grabs.json). Przeanalizuj:

1. Ile unikalnych IP? Ile używa VPN/Tor/Proxy?
2. Które wpisy są podejrzane? Podaj risk_score, tagi i suspicion_reason.
3. Pogrupuj po krajach — czy widać wzorce geograficzne?
4. Oceń burst_activity — czy ktoś skanuje automatycznie?
5. Porównaj session_hash — czy ten sam użytkownik wraca pod różnymi IP?
6. Na końcu daj listę IP i User-Agentów do blokowania.

Używaj pól: risk_score, tags, suspicion_reason, burst_activity, session_hash, asn.
```

---

## 📲 Telegram — przykładowy alert

```
🕵️ IP GRAB — 🔴 WYSOKI (score: 90)
──────────────────
🆔 grab_6820a1b2c3d4f
🕐 2026-05-04 02:00:01
🌐 IP: 185.220.101.45
📍 🇳🇱 Amsterdam, North Holland, NL
🏢 ISP: M247 Ltd
🔖 ASN: AS9009
🏷 Tagi: vpn, proxy, datacenter
⚠️ Powód: VPN + Proxy + Datacenter ASN/ISP
📡 Burst: NIE
📲 Źródło: direct
🌍 Referer: Direct
🤖 UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64)...
🗺 Mapa: https://maps.google.com/...
[📍 Lokalizacja — pinezka na mapie]
```

---

## ⚠️ Legal

Używaj wyłącznie na **własnej infrastrukturze** i **własnych projektach**.  
Logowanie adresów IP jest dopuszczalne w celach bezpieczeństwa i analitycznych przy posiadaniu odpowiedniej podstawy prawnej (RODO/GDPR).  
Nie używaj do śledzenia osób bez ich wiedzy.

---

<div align="center">
Made with 🖤 by <b>OG ENCRO</b>
</div>
