# Gaming Lounge Management & Automation System (GLMAS)
## System Architecture & Technical Specification (v2.0)
### TV-Centric Pure IP Control & Decoupled Console Architecture

---

## 1. Architectural Pivot & System Overview

Version 2.0 shifts the physical enforcement and monitoring layer from direct PlayStation 5 power management to **Pure IP-Based Smart TV Control (Hisense VIDAA / Android TV / Google TV)** while keeping the PS5 consoles powered continuously in the background.

### 1.1. Core Motivations for the TV-Centric Shift
1. **Zero Hardware Budget ($0 Cost):** Uses existing lounge local area network (LAN), Ethernet/Wi-Fi, Wake-on-LAN (WoL), and TV OS IP daemons. No smart plugs, relays, or microcontrollers needed.
2. **Instant Customer Handoff:** PS5 consoles remain powered on 24/7. When a customer pays, the TV turns on instantly to the home screen with zero cold-boot delay.
3. **Continuous Game Patching:** PS5s can download multi-gigabyte title updates in the background without needing cashier intervention or interrupting sessions.
4. **Zero Risk of Storage Corruption:** Eliminates filesystem corruption risks on console NVMe storage caused by sudden power cuts or force-sleep cycles.
5. **Universal Visual Lockout:** Turning off the display physically halts play immediately—customers cannot see or play once their session expires.

---

## 2. High-Level Architecture Diagram

```
                    ┌────────────────────────────────────────┐
                    │        Cashier / Admin Web UI          │
                    │   (Tablet, Desktop, Mobile Browser)    │
                    └───────────────────▲────────────────────┘
                                        │ WebSocket (Real-Time State Sync)
                                        │ REST API (Session Commands)
                                        ▼
┌────────────────────────────────────────────────────────────────────────────┐
│                       Central Host Server (Local PC)                       │
│                                                                            │
│  ┌──────────────────────┐  ┌───────────────────────┐  ┌─────────────────┐  │
│  │   Session Engine     │  │ Rate & Interval Engine│  │ Shift & POS Mod │  │
│  └──────────┬───────────┘  └──────────┬────────────┘  └────────┬────────┘  │
│             │                         │                        │           │
│             └────────────────┬────────┴────────────────────────┘           │
│                              ▼                                             │
│                 ┌───────────────────────────┐                              │
│                 │   SQLite / PostgreSQL DB  │                              │
│                 └────────────▲──────────────┘                              │
│                              │                                             │
│                 ┌────────────┴──────────────┐                              │
│                 │ State Reconciliation Daemon│                             │
│                 └────────────▲──────────────┘                              │
│                              │                                             │
│                 ┌────────────┴──────────────┐                              │
│                 │ TV IP Poller & WoL Daemon │                              │
│                 │ (MQTT / ADB / REST / WoL) │                              │
│                 └────────────┬──────────────┘                              │
└──────────────────────────────┼─────────────────────────────────────────────┘
                               │ Local Gigabit LAN (Static IP Pool)
                               │
        ┌──────────────────────┼──────────────────────┐
        ▼                      ▼                      ▼
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│ Station 1 TV │       │ Station 2 TV │       │ Station N TV │
│ (Hisense IP) │       │ (Hisense IP) │       │ (Hisense IP) │
└───────┬──────┘       └───────┬──────┘       └───────┬──────┘
        │ HDMI                  │ HDMI                  │ HDMI
        │ (Power-Off Link: OFF) │ (Power-Off Link: OFF) │ (Power-Off Link: OFF)
        ▼                       ▼                       ▼
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│ PS5 #1 (ON)  │       │ PS5 #2 (ON)  │       │ PS5 #N (ON)  │
│ Background   │       │ Background   │       │ Background   │
└──────────────┘       └──────────────┘       └──────────────┘
```

---

## 3. Station Session State Machine

Each station follows a deterministic lifecycle driven by cashier actions, timers, and TV power states:

```
                      ┌────────────────────────────────┐
                      │                                │
                      ▼                                │
               ┌──────────────┐                        │
               │  AVAILABLE   │                        │
               │  (TV Screen  │                        │
               │     OFF)     │                        │
               └──────┬───────┘                        │
                      │                                │
        Start Session │ (Prepaid / Postpaid)           │
       Server sends   │ Wake-on-LAN to TV              │
                      ▼                                │
               ┌──────────────┐                        │
               │    ACTIVE    │◄───────────────┐       │
               │  (TV Screen  │                │       │
               │     ON)      │                │       │
               └──┬───┬───┬───┘                │       │
                  │   │   │                    │       │
     Manual Pause │   │   │ Unexpected TV Off  │ Resume│
                  │   │   └──────────────┐     │       │
                  │   │                  ▼     │       │
                  │   │           ┌────────────┴─┐     │
                  │   │           │    PAUSED    │     │
                  │   │           │ (Timer Stop) │     │
                  │   │           └──────────────┘     │
                  │   │                                │
      Time Expired│   │ Manual Tab Close               │
      Server sends│   │ Server turns TV Screen OFF     │
      IP Sleep to │   │                                │
      TV Screen   ▼   ▼                                │
               ┌──────────────┐                        │
               │   PAYMENT    │                        │
               │   PENDING    │                        │
               └──────┬───────┘                        │
                      │                                │
       Payment Settled│ & Receipt Cleared              │
                      └────────────────────────────────┘
```

### Station State Matrix

| State | TV Power Status | PS5 Hardware State | Billing Engine Action |
| :--- | :--- | :--- | :--- |
| **AVAILABLE (Idle)** | `STANDBY / OFF` | `AWAKE / RUNNING` | Clocks inactive; Poller monitors TV IP for unauthorized power-ons. |
| **ACTIVE (Prepaid)** | `SCREEN ON` | `AWAKE / RUNNING` | Ticks down $T_{	ext{remaining}} 	o 0$; at $0$, sends IP turn-off command to TV. |
| **ACTIVE (Postpaid)** | `SCREEN ON` | `AWAKE / RUNNING` | Ticks up $T_{	ext{elapsed}}$; updates live monetary fee every minute. |
| **PAUSED** | `STANDBY / OFF` | `AWAKE / RUNNING` | Billing frozen; max pause buffer enforced (e.g., 15 min max). |
| **PAYMENT PENDING** | `STANDBY / OFF` | `AWAKE / RUNNING` | Display blacked out; invoice locked pending cashier settlement. |
| **MAINTENANCE** | `SCREEN ON` | `AWAKE / RUNNING` | Billing bypassed; alerts suppressed for staff maintenance. |

---

## 4. Configurable Rate Matrix & Sliced Billing Engine

Pricing is calculated using **discrete time intervals (slices)**. When players request additional controllers mid-session, the engine closes the active slice and opens a new slice without resetting overall time.

### 4.1. Configuration Structure

* **Controller Tier Definitions:**
  * `Tier 1 (Solo/Duo)`: 1–2 Controllers $	o$ Base Rate (e.g., $\$4.00	ext{ / hr}$)
  * `Tier 2 (Multiplayer)`: 3–4 Controllers $	o$ Group Rate (e.g., $\$7.00	ext{ / hr}$)
* **Station Modifiers:**
  * `Standard Lounge`: 1.0x Base Multiplier
  * `VIP Private Booth`: 1.5x Multiplier
* **Grace Period & Rounding Policies:**
  * **Initial Setup Buffer:** 3 minutes unbilled grace period at session start.
  * **Postpaid Minimum Charge:** 15 minutes minimum billable time.
  * **Rounding Interval:** Rounded up to the nearest 5-minute block on checkout.

### 4.2. Mathematical Billing Formula

$$	ext{Grand Total} = \sum_{i=1}^{n} \left( rac{	ext{Duration}_i 	ext{ (minutes)}}{60} 	imes 	ext{Tier Rate}_i 	imes 	ext{Station Multiplier} ight) + \sum 	ext{Retail Items} - 	ext{Discounts}$$

#### Example Calculation:
* 15:00 – 15:45 (45 min): 2 Controllers ($4.00/hr) $\implies (45/60) 	imes 4.00 = \$3.00$
* 15:45 – 17:00 (75 min): 4 Controllers ($7.00/hr) $\implies (75/60) 	imes 7.00 = \$8.75$
* Retail Add-on: 2 Cans of Red Bull @ $2.50 = $5.00
* **Total Due at 17:00:** $\$3.00 + \$8.75 + \$5.00 = \mathbf{\$16.75}$

---

## 5. Pure IP TV Control Protocols (Hisense Focus)

Because all control occurs over IP with zero extra hardware, the system utilizes TV OS network control protocols.

### 5.1. Protocol Matrix by Hisense OS

| Hisense OS | Turn ON (Wake) | Turn OFF (Screen Standby) | Polling / State Check |
| :--- | :--- | :--- | :--- |
| **VIDAA OS** *(Most Common)* | Wake-on-LAN (UDP Port 9 to TV MAC) | MQTT over TLS to Port `36669` (Sends `KEY_POWER` / `KEY_STANDBY` payload) | TCP Port `36669` Probe (Open = Screen ON, Closed = Standby) |
| **Android TV / Google TV** | Wake-on-LAN (UDP Port 9 to TV MAC) | ADB command (`adb shell input keyevent 26`) on Port `5555` or Google TV Remote v2 on Port `6466` | TCP Port `5555` / `6466` Handshake |
| **Roku OS** | Wake-on-LAN (UDP Port 9 to TV MAC) | HTTP REST: `POST http://<TV_IP>:8060/keypress/PowerOff` | `GET http://<TV_IP>:8060/query/device-info` (Checks `<power-mode>`) |

---

### 5.2. Mandatory Hardware & HDMI Decoupling Settings

To ensure the TV turns off **without** turning off the PS5:

#### On Every PlayStation 5 Console:
1. Go to **Settings $	o$ System $	o$ HDMI**.
2. Set **Enable HDMI Device Link** $	o$ **OFF** (or set **Enable Power Off Link** $	o$ **OFF**).
3. Go to **Settings $	o$ System $	o$ Power Saving $	o$ Set Time Until PS5 Enters Rest Mode** $	o$ Set to **Don't Put in Rest Mode** (or 5+ hours idle timeout).

#### On Every Hisense TV:
1. Go to **Settings $	o$ System $	o$ CEC / HDMI Control** $	o$ Set **Device Auto Power Off** to **OFF**.
2. Go to **Settings $	o$ Network $	o$ TV Auto Power On / Wake on LAN** $	o$ Set to **ON**.
3. Go to **Settings $	o$ System $	o$ Advanced $	o$ Fast Boot / Quick Power-On** $	o$ Set to **ON** (preserves network listening in standby).
4. Assign a **Static IP Reservation** for every TV's MAC address in the router.

---

## 6. Network Polling & Rogue Screen Detection

The server runs an asynchronous background task checking TV states every 10–15 seconds.

```
                    ┌────────────────────────────┐
                    │ Poller Query (Every 10-15s)│
                    └─────────────┬──────────────┘
                                  │
                                  ▼
                    ┌────────────────────────────┐
                    │     Probe TV Port/API      │
                    │   (36669 / 5555 / 8060)    │
                    └─────────────┬──────────────┘
                                  │
                                  ▼
                    ┌────────────────────────────┐
                    │    Inspect Response        │
                    │ (PORT OPEN vs CLOSED/TOUT) │
                    └─────────────┬──────────────┘
                                  │
                                  ▼
                    ┌────────────────────────────┐
                    │   Fetch Database State     │
                    │ (AVAILABLE, ACTIVE, PAUSED)│
                    └─────────────┬──────────────┘
                                  │
                                  ▼
                         [ Matches Expected? ]
                                  │
                 ┌────────────────┴────────────────┐
                 │ YES                             │ NO
                 ▼                                 ▼
         ┌───────────────┐                 [ Evaluation ]
         │ Normal State  │                         │
         │ Cycle Cleared │        ┌────────────────┴────────────────┐
         └───────────────┘        │                                 │
                                  ▼                                 ▼
                         [ TV ON while IDLE? ]             [ TV OFF while ACTIVE? ]
                                  │                                 │
                                  ▼                                 ▼
                        ┌───────────────────┐             ┌───────────────────┐
                        │   ROGUE SCREEN    │             │ UNEXPECTED TV OFF │
                        │ 1. Debounce (2x)  │             │ 1. Auto-Pause Tab │
                        │ 2. Flag UI Alert  │             │ 2. Alert Cashier  │
                        │ 3. 2m Auto-Cutoff │             │ 3. Wait / Resume  │
                        └───────────────────┘             └───────────────────┘
```

### 6.1. Rogue Detection & Remediation Rules

* **Rogue Screen Alert (TV = `ON` & DB = `AVAILABLE / IDLE`):**
  * **Cause:** A customer turns on the TV using the physical remote or button without paying.
  * **Debounce:** Waits 2 consecutive polling cycles (20 seconds) to verify state.
  * **Cashier Notification:** Station Card flashes red with an audible chime: *"⚠️ Station 4: TV Turned On Without Active Session"*.
  * **Cashier Actions:**
    * `[ Start Session Here ]`: Starts an active tab backdated to the detected power-on timestamp.
    * `[ Force Turn Off ]`: Transmits an immediate IP sleep payload to black out the TV.
    * `[ Maintenance Mode ]`: Suppresses alerts for staff testing.
  * **Auto-Cutoff Safeguard:** If the cashier does not respond within **2 minutes**, the server automatically sends the IP power-off payload to cut the screen.

* **Unexpected Screen Cut (TV = `STANDBY` & DB = `ACTIVE`):**
  * **Cause:** Customer accidentally hits the TV power button or cable disconnects.
  * **Action:** Billing timer pauses automatically so customers are not charged while waiting for assistance.
  * **UI Update:** Station Card shows *"TV Suspended — Timer Paused"*.

---

## 7. Cashier Dashboard UI/UX Specification

The cashier interface is a real-time visual grid of cards representing each station.

### 7.1. Station Card Visual States

* 🟢 **Green (Available / Screen Off):**
  * Card Header: "Station 03 — Standard"
  * Display Status: `TV OFF (Ready)`
  * Action: Prominent `[ Start Session ]` button opening the setup modal.
* 🔵 **Blue (Active — Prepaid Countdown):**
  * Card Header: "Station 01 — 2 Controllers"
  * Dynamic Display: Large countdown timer (e.g., `38:22` remaining).
  * Payment Badge: `PAID` (Green) or `PAY ON EXIT` (Yellow).
  * Quick Buttons: `[ +15m ]` `[ +30m ]` `[ +1h ]` `[ Switch Tier ]` `[ Add Snack ]` `[ Stop ]`.
* 🟣 **Purple (Active — Postpaid Open Tab):**
  * Card Header: "Station 05 — 4 Controllers"
  * Dynamic Display: Elapsed timer (e.g., `01:42:10`) and Live Bill Total (e.g., `$12.80`).
  * Quick Buttons: `[ Switch Tier ]` `[ Add Snack ]` `[ Pause ]` `[ Finish & Settle ]`.
* 🟡 **Yellow (Expiring Warning):**
  * Active when prepaid time drops below 5 minutes.
  * Pulses gently on the cashier screen to prompt the cashier for extensions.
* 🔴 **Red (Rogue / Unauthorized Screen Detected):**
  * Flashing red banner: `⚠️ UNAUTHORIZED TV ON`.
  * Timer showing unbilled active duration.
  * Quick Buttons: `[ Start Tab ]` `[ Force Blackout ]`.

---

## 8. Database Schema & Data Models

```
┌──────────────────┐       ┌────────────────────────┐       ┌──────────────────────┐
│     stations     │       │     pricing_tiers      │       │       sessions       │
├──────────────────┤       ├────────────────────────┤       ├──────────────────────┤
│ id (PK)          │       │ id (PK)                │       │ id (PK)              │
│ station_number   │       │ name                   │       │ station_id (FK)      │
│ tv_ip_address    │       │ controller_count (1..4)│       │ session_type         │
│ tv_mac_address   │       │ hourly_rate            │       │ status               │
│ tv_os_type       │       │ is_active              │       │ start_time           │
│ tv_auth_token    │       └───────────┬────────────┘       │ end_time             │
│ current_state    │                   │                    │ total_amount         │
└────────┬─────────┘                   │                    │ payment_status       │
         │                             │                    └──────────┬───────────┘
         │                             │                               │
         │                             │        ┌──────────────────────┼──────────────────────┐
         │                             │        │                      │                      │
         ▼                             ▼        ▼                      ▼                      ▼
┌──────────────────┐       ┌────────────────────────┐       ┌──────────────────────┐┌─────────────────┐
│  station_audits  │       │   session_intervals    │       │     order_items      ││  transactions   │
├──────────────────┤       ├────────────────────────┤       ├──────────────────────┤├─────────────────┤
│ id (PK)          │       │ id (PK)                │       │ id (PK)              ││ id (PK)         │
│ station_id (FK)  │       │ session_id (FK)        │       │ session_id (FK)      ││ session_id (FK) │
│ event_type       │       │ pricing_tier_id (FK)   │       │ item_name            ││ shift_id (FK)   │
│ tv_physical_state│       │ started_at             │       │ unit_price           ││ amount          │
│ expected_state   │       │ ended_at               │       │ quantity             ││ payment_method │
│ resolved_action  │       │ subtotal_amount        │       │ subtotal             ││ cashier_id      │
│ created_at       │       └────────────────────────┘       └──────────────────────┘│ timestamp        │
└──────────────────┘                                                                └─────────────────┘
```

### Table Definitions

1. **`stations`**: Stores TV network parameters (`tv_ip_address`, `tv_mac_address`), OS type (`VIDAA`, `ANDROID`, `ROKU`), auth tokens, and current operational states.
2. **`pricing_tiers`**: Stores hourly rates mapped to controller configurations (e.g., 2-controller vs 4-controller).
3. **`sessions`**: Root customer transaction record linking start/end timestamps, total fees, and payment status.
4. **`session_intervals`**: Captures sliced billing periods whenever controller counts switch mid-game.
5. **`order_items`**: Tracks POS retail snacks, drinks, or extra rentals attached to a session tab.
6. **`transactions`**: Financial records tracking payment tenders (Cash, Card), cashier IDs, and shift IDs.
7. **`station_audits`**: Complete audit log of TV power events, rogue screen alerts, auto-blackout enforcements, and cashier overrides.

---

## 9. Shift Management, POS & Accounting Controls

* **Shift Open / Close Procedures:** Cashiers input starting drawer cash float. System aggregates all cash collected, card payments, and POS add-ons throughout the shift.
* **Blind Drop Reconciliation:** At shift end, cashiers enter counted cash before seeing the system expected total. Discrepancies are logged in shift reports.
* **Audit Protection:** Manual rate overrides, custom discounts, or rogue alert suppressions require cashier confirmation and are stored in `station_audits`.

---

## 10. Resilience & Edge Case Handling Matrix

| Scenario | System Behavior & Fallback |
| :--- | :--- |
| **Server Crash or Power Dip** | Upon server reboot, the daemon queries all active sessions from the database and immediately polls all TV IPs. Timers calculate elapsed time using server clock timestamps ($T_{	ext{now}} - T_{	ext{start}}$) so zero billable time is lost. |
| **TV Drops Off Network** | Poller flags `NO RESPONSE`. Billing timer enters protected pause mode. Cashier UI highlights station with network warning. |
| **Prepaid Time Expires** | At $T = 0$, the server fires the IP turn-off payload to the TV. If "Allow Overtime" was toggled on by cashier, the session seamlessly switches into Postpaid mode at the active tier rate. |
| **Station Transfer** | Cashier clicks `[ Move Station ]`. Server turns off Source TV, turns on Destination TV via Wake-on-LAN, and transfers the active session record and accumulated intervals. |

---
*Document Version: 2.0.0 — Specification for Pure IP TV Control & Decoupled Consoles*
