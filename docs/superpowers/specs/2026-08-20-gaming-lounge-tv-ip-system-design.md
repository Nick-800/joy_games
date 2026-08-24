# Gaming Lounge Management & Automation System (GLMAS v2.0)
## TV-Centric Pure IP Control & Decoupled Console Architecture Specification

## 1. System Overview & Objectives
**Joy Games GLMAS v2.0** pivots the physical enforcement and monitoring layer from direct PS5 power control to **Pure IP-Based Smart TV Control (Hisense VIDAA OS / Android TV / Google TV / Roku OS)** while keeping PlayStation 5 consoles running continuously in the background.

### Core Objectives & Motivations:
- **$0 Hardware Budget:** Pure IP control using existing LAN, Wake-on-LAN (UDP Port 9), and native TV OS IP daemons. No smart plugs, relays, or microcontrollers needed.
- **Zero Cold-Boot Delay:** PS5 consoles stay powered on 24/7. When a customer starts a session, the TV turns on instantly to the home screen.
- **Background Game Updates:** Consoles download patches without cashier intervention or session disruption.
- **Zero NVMe Storage Corruption Risk:** Eliminates filesystem corruption on console storage caused by sudden power cuts or force-sleep cycles.
- **Instant Visual Lockout:** Turning off the TV screen physically halts play immediately—customers cannot see or play once their session expires.
- **Deterministic Sliced Billing in Libyan Dinar (`LYD` / `د.ل`):** Integer millimes (1 LYD = 1,000 Millimes) accounting across multi-tier controller switches, grace periods, minimum charge, and VIP multipliers.

---

## 2. TV Hardware Driver Architecture

```
┌────────────────────────────────────────────────────────────────────────────┐
│                    GLMAS Core Application (Laravel 13)                     │
│                                                                            │
│  ┌───────────────────────┐  ┌───────────────────────┐  ┌────────────────┐  │
│  │   SessionManager      │  │      RateEngine       │  │  ShiftLedger   │  │
│  │ (Prepaid / Postpaid)  │  │(Sliced Intervals / LYD│  │  (POS & Cash)  │  │
│  └───────────┬───────────┘  └───────────┬───────────┘  └────────┬───────┘  │
│              │                          │                       │          │
│              └─────────────────┬────────┴───────────────────────┘          │
│                                ▼                                           │
│                  ┌───────────────────────────┐                             │
│                  │  Eloquent SQLite / DB     │                             │
│                  └─────────────▲─────────────┘                             │
│                                │                                           │
│                  ┌─────────────┴─────────────┐                             │
│                  │ TvReconciliationService   │                             │
│                  └─────────────▲─────────────┘                             │
│                                │                                           │
│                  ┌─────────────┴─────────────┐                             │
│                  │ TvDeviceDriverInterface   │                             │
│                  │  ├─ SimulatedTvDriver     │                             │
│                  │  ├─ HisenseVidaaTvDriver  │                             │
│                  │  ├─ AndroidTvDriver       │                             │
│                  │  └─ RokuTvDriver          │                             │
│                  └─────────────┬─────────────┘                             │
└────────────────────────────────┼───────────────────────────────────────────┘
                                 │
                   Local Gigabit LAN (Static IP Pool)
```

### TV Driver Protocol Matrix:
1. **`HisenseVidaaTvDriver` (Hisense VIDAA OS):**
   - **Turn ON (Wake):** Wake-on-LAN (UDP broadcast / direct unicast to UDP Port 9 with TV MAC address).
   - **Turn OFF (Screen Standby):** MQTT message to port `36669` or TCP standby handshake payload.
   - **Polling / State Check:** TCP Port `36669` socket probe (Open = Screen ON, Closed / Refused = Standby).
2. **`AndroidTvDriver` (Android TV / Google TV):**
   - **Turn ON (Wake):** Wake-on-LAN (UDP Port 9 to TV MAC).
   - **Turn OFF (Screen Standby):** ADB command `adb shell input keyevent 26` on Port `5555` or Google TV Remote v2 protocol on Port `6466`.
   - **Polling / State Check:** TCP Port `5555` / `6466` probe.
3. **`RokuTvDriver` (Roku OS):**
   - **Turn ON (Wake):** Wake-on-LAN (UDP Port 9 to TV MAC).
   - **Turn OFF (Screen Standby):** `POST http://<TV_IP>:8060/keypress/PowerOff`.
   - **Polling / State Check:** `GET http://<TV_IP>:8060/query/device-info` (checks `<power-mode>`).
4. **`SimulatedTvDriver`:**
   - In-memory / database software simulation of TV Screen ON, Standby, Rogue remote press, and network loss for instant development & testing.

---

## 3. Database Schema Updates

### 3.1. `stations`
- `id` (PK)
- `name` (string, e.g. "PS5 Station 01")
- `station_number` (integer, unique)
- `type` (`standard`, `vip`, default `standard`)
- `tv_ip_address` (string, nullable)
- `tv_mac_address` (string, nullable)
- `tv_os_type` (`enum: vidaa, android, roku, simulated`, default `simulated`)
- `tv_auth_token` (string, nullable)
- `tv_physical_state` (`enum: standby, screen_on, unreachable`, default `standby`)
- `current_state` (`enum: available, active_prepaid, active_postpaid, paused, payment_pending, maintenance`, default `available`)
- `consecutive_on_pings` (integer, default 0)
- `first_detected_on_at` (timestamp, nullable)
- `last_ping_at` (timestamp, nullable)
- `notes` (text, nullable)
- `is_active` (boolean, default true)
- `timestamps`

### 3.2. `station_audits`
- `event_type`: `rogue_screen_detected`, `auto_blackout_triggered`, `manual_tv_off`, `tv_wake_sent`, `unexpected_tv_off`, `price_override`, `station_transfer`, `tier_switched`.

---

## 4. State Reconciliation & Rogue Screen Detection
The background daemon polls all TV IPs every 10–15 seconds:

1. **Rogue Screen Alert (`TV = SCREEN_ON` & `DB = AVAILABLE`):**
   - Customer or staff turned on the TV using the remote or TV button without starting a tab.
   - **2-Ping Debounce:** Requires 2 consecutive positive pings (20 seconds) before triggering.
   - **Cashier Alert:** Station Card flashes red with *"⚠️ UNAUTHORIZED TV ON"* and duration timer.
   - **Cashier Actions:** `[ Start Session Here ]` (backdates start time to detected power-on) or `[ Force Blackout ]`.
   - **Auto-Cutoff Safeguard:** If cashier takes no action within **2 minutes** (120 seconds), the system automatically transmits the IP power-off payload to cut the screen.
2. **Unexpected Screen Cut (`TV = STANDBY` & `DB = ACTIVE`):**
   - Customer accidentally turned off the TV or cable disconnected.
   - Billing timer automatically pauses to protect customer from unfair charges.
3. **Prepaid Expiration:**
   - At $T_{\text{remaining}} = 0$, system sends turn-off IP payload to black out the TV unless *"Allow Overtime"* is enabled (which transitions dynamically to Postpaid).
