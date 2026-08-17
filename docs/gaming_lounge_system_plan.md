# Gaming Lounge Management & Automation System (GLMAS)
## System Architecture, Logic & Implementation Specification

---

## 1. Executive Summary & System Overview

The **Gaming Lounge Management & Automation System (GLMAS)** is a specialized, local-first management platform designed to automate time tracking, multi-tier billing, point-of-sale (POS) operations, and hardware power control for PlayStation 5 (PS5) gaming stations.

### Core Objectives
1. **Automated Time & Billing Tracking:** Eliminate manual stopwatch errors and billing disputes via deterministic prepaid countdowns and open postpaid tabs.
2. **Dynamic Pricing Flexibility:** Support differentiated pricing based on active controller counts (e.g., 2 vs. 3–4 players) with dynamic mid-session tier splitting.
3. **100% Software-Driven Console Automation:** Utilize native PlayStation local network discovery and control protocols to wake consoles and trigger graceful sleep states over Ethernet without external relays or smart plugs.
4. **State Reconciliation & Rogue Session Detection:** Continuously poll the physical power states of all consoles on the LAN, matching them against database session states to detect and resolve unauthorized ("ghost") play immediately.
5. **Integrated Financial Integrity:** Track every billable minute, retail add-on, shift drawer opening/closing, and cashier transaction to eliminate revenue leakage.

---

## 2. High-Level System Architecture

The architecture operates locally within the gaming lounge LAN to ensure sub-millisecond response times, immunity to internet outages, and low-latency hardware packet delivery.

```
                    ┌────────────────────────────────────────┐
                    │       Cashier / Admin Web UI           │
                    │   (Tablet, Desktop, Mobile Browser)    │
                    └───────────────────▲────────────────────┘
                                        │ WebSocket (Live Updates)
                                        │ REST API (Commands)
                                        ▼
┌────────────────────────────────────────────────────────────────────────────┐
│                       Central Host Server (Local)                          │
│                                                                            │
│  ┌──────────────────────┐  ┌───────────────────────┐  ┌─────────────────┐  │
│  │   Session Manager    │  │ Rate & Billing Engine │  │ Shift / POS Ledg│  │
│  └──────────┬───────────┘  └──────────┬────────────┘  └────────┬────────┘  │
│             │                         │                        │           │
│             └────────────────┬────────┴────────────────────────┘           │
│                              ▼                                             │
│                 ┌───────────────────────────┐                              │
│                 │   SQLite / PostgreSQL DB  │                              │
│                 └────────────▲──────────────┘                              │
│                              │                                             │
│                 ┌────────────┴──────────────┐                              │
│                 │ State Reconciliation Engine│                             │
│                 └────────────▲──────────────┘                              │
│                              │                                             │
│                 ┌────────────┴──────────────┐                              │
│                 │ Hardware Poller & Control │                              │
│                 │   (UDP/TCP Socket Daemon) │                              │
│                 └────────────┬──────────────┘                              │
└──────────────────────────────┼─────────────────────────────────────────────┘
                               │
               Local Wired Gigabit LAN (Static IPs)
                               │
        ┌──────────────────────┼──────────────────────┐
        ▼                      ▼                      ▼
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│ PS5 Station 1│       │ PS5 Station 2│       │ PS5 Station N│
│ 192.168.1.101│       │ 192.168.1.102│       │ 192.168.1.10N│
└──────────────┘       └──────────────┘       └──────────────┘
```

---

## 3. Session State Machine & Core Logic

Each gaming station operates under a strict finite-state machine (FSM).

```
                      ┌────────────────────────────────┐
                      │                                │
                      ▼                                │
               ┌──────────────┐                        │
               │  AVAILABLE   │                        │
               │    (IDLE)    │                        │
               └──────┬───────┘                        │
                      │                                │
        Start Session │ (Prepaid / Postpaid)           │
                      ▼                                │
               ┌──────────────┐                        │
               │    ACTIVE    │◄───────────────┐       │
               │ (RUNNING)    │                │       │
               └──┬───┬───┬───┘                │       │
                  │   │   │                    │       │
     Session Pause│   │   │ Unexpected Sleep   │ Resume│
                  │   │   └──────────────┐     │       │
                  │   │                  ▼     │       │
                  │   │           ┌────────────┴─┐     │
                  │   │           │    PAUSED    │     │
                  │   │           │  (PROTECTED) │     │
                  │   │           └──────────────┘     │
                  │   │                                │
      Time Expired│   │ Manual Finish                  │
                  ▼   ▼                                │
               ┌──────────────┐                        │
               │   PAYMENT    │                        │
               │   PENDING    │                        │
               └──────┬───────┘                        │
                      │                                │
       Payment Settled│ & Cleared                      │
                      └────────────────────────────────┘
```

### Station State Definitions

| State | Description | Console Status | Timer Engine Behavior |
| :--- | :--- | :--- | :--- |
| **AVAILABLE (Idle)** | Station is clean and ready for a new customer. | `STANDBY` | Timer inactive. Poller monitors for unauthorized wakeups. |
| **ACTIVE (Prepaid)** | Fixed-duration session (e.g., 60 min). | `AWAKE` | Ticks down $T_{	ext{remaining}} 	o 0$. Triggers warning at 5 min. Puts console to sleep at 0 min. |
| **ACTIVE (Postpaid)** | Open-ended session ("Running Tab"). | `AWAKE` | Ticks up $T_{	ext{elapsed}}$ from start. Calculates live monetary fee every minute. |
| **PAUSED** | Customer break or temporary console disconnect. | `STANDBY` / `AWAKE` | Clock stopped. Billable time frozen. Max pause buffer enforced (e.g., 15 min max). |
| **PAYMENT PENDING** | Session completed; hardware asleep; awaiting cash/card settlement. | `STANDBY` | Timer stopped. Bill locked. Station cannot be booked until invoice is closed. |
| **MAINTENANCE** | Staff updates, game downloads, or hardware checks. | `AWAKE` | Billing disabled. Poller ignores `AWAKE` alerts for this station. |

---

## 4. Configurable Rate Matrix & Sliced Billing Engine

Pricing is calculated via discrete **time intervals (slices)**. This allows seamless transitions if players add/remove controllers or if rates change across time-of-day boundaries.

### 4.1. Pricing Configuration Schema

* **Controller Tiers:**
  * `Tier 1 (Solo/Duo)`: 1–2 Controllers $	o$ Base Rate (e.g., $\$4.00	ext{ / hr}$)
  * `Tier 2 (Group)`: 3–4 Controllers $	o$ Multi-Player Rate (e.g., $\$7.00	ext{ / hr}$)
* **Station Categories:**
  * `Standard Desk`: 1.0x Rate Multiplier
  * `VIP Lounge / Private Room`: 1.5x Rate Multiplier
* **Billing Granularity & Rounding Policies:**
  * **Initial Grace Period:** 3 minutes (unbilled time at session start for game launching/login).
  * **Postpaid Minimum Charge:** 15 minutes minimum billable time.
  * **Postpaid Rounding Step:** Rounded to nearest 5 minutes (or ceiling 15 minutes, configurable).

### 4.2. Mathematical Model for Sliced Interval Billing

If a session undergoes rate changes mid-game (e.g., friends join after 30 minutes), the total fee is computed as:

$$	ext{Total Session Fee} = \sum_{i=1}^{n} \left( rac{	ext{Duration}_i 	ext{ (minutes)}}{60} 	imes 	ext{Rate}_i ight) + \sum 	ext{POS Add-ons} - 	ext{Discounts}$$

#### Example Billing Scenario:
1. **16:00 – 16:30 (30 min):** 2 Players on Station 1 ($4.00/hr) $\implies (30/60) 	imes 4.00 = \$2.00$
2. **16:30 – 17:30 (60 min):** Switched to 4 Players ($7.00/hr) $\implies (60/60) 	imes 7.00 = \$7.00$
3. **Retail Add-ons:** 2 Sodas ($2.00) + 1 Chips ($1.50) $\implies \$3.50$
4. **Final Bill at 17:30:** $\$2.00 + \$7.00 + \$3.50 = \mathbf{\$12.50}$

---

## 5. Hardware Network Automation & State Polling Layer

The system eliminates third-party smart plugs by interacting directly with the PS5 operating system over the local area network.

### 5.1. Network Infrastructure Prerequisites
* **Static IP Assignments:** All PS5 consoles are assigned fixed IP addresses via DHCP MAC reservation on the router (e.g., `192.168.1.101` through `192.168.1.120`).
* **Wired Gigabit Ethernet:** Direct Cat6 Ethernet connections to prevent packet dropouts and latency associated with wireless power-saving modes.
* **Console Configuration:** PS5 System Settings $	o$ *System* $	o$ *Power Saving* $	o$ *Features Available in Rest Mode* $	o$ Enable **"Stay Connected to the Internet"** and **"Enable Turning On PS5 from Network"**.

### 5.2. Network Discovery & Control Mechanics

| Command / Action | Network Mechanism | Payload / Protocol | Result |
| :--- | :--- | :--- | :--- |
| **Wake from Standby** | UDP Broadcast / Direct Unicast | Wake packet to UDP Port `9302` / `987` containing paired client credentials | Console boots to main dashboard; TV wakes via HDMI-CEC |
| **Graceful Standby** | Authenticated TCP/HTTP REST command | Standby control payload sent to PS5 OS daemon | Console saves OS state and enters Rest Mode safely |
| **State Polling** | UDP Discovery Ping / TCP Handshake | Discovery packet sent to PS5 status port every 10–15 seconds | Console returns status header containing `status: "STANDBY"` or `status: "AWAKE"` |

---

## 6. State Reconciliation & Rogue Session Detection

The central server runs an asynchronous background daemon that performs **State Reconciliation** every 10–15 seconds.

```
                    ┌────────────────────────────┐
                    │ Poller Query (Every 10-15s)│
                    └─────────────┬──────────────┘
                                  │
                                  ▼
                    ┌────────────────────────────┐
                    │    Inspect Physical State  │
                    │   (AWAKE, STANDBY, TIMEOUT)│
                    └─────────────┬──────────────┘
                                  │
                                  ▼
                    ┌────────────────────────────┐
                    │   Fetch Database State     │
                    │ (IDLE, ACTIVE, PAUSED, etc)│
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
                         [ AWAKE on IDLE? ]                [ STANDBY on ACTIVE? ]
                                  │                                 │
                                  ▼                                 ▼
                        ┌───────────────────┐             ┌───────────────────┐
                        │   ROGUE SESSION   │             │ UNEXPECTED SLEEP  │
                        │ 1. Debounce (2x)  │             │ 1. Auto-Pause Tab │
                        │ 2. Flag UI Alert  │             │ 2. Alert Cashier  │
                        │ 3. Auto-Sleep Rule│             │ 3. Wait / Resume  │
                        └───────────────────┘             └───────────────────┘
```

### 6.1. Rogue Detection Scenarios & Remediation

1. **Rogue Detection (Physical = `AWAKE` & Database = `AVAILABLE / IDLE`):**
   * **Trigger:** Customer or staff turns on a PS5 manually using the physical controller/console button without notifying the cashier.
   * **Debounce Filter:** Poller requires 2 consecutive positive `AWAKE` pings (20–30s) to filter out background OS cloud update wakeups.
   * **Alerting:** Cashier dashboard immediately pulses Station Card in flashing Amber/Red with an audible chime: *"Station 3: Unauthorized Power-On Detected"*.
   * **Resolution Options on UI:**
     * `[Start Session Here]`: Initializes a new tab, automatically setting start time to the first detected wakeup timestamp.
     * `[Force Sleep]`: Sends a remote Rest Mode command to shut down the console immediately.
     * `[Set Maintenance]`: Marks station as maintenance for staff usage.
   * **Automated Guardrail:** If cashier does not act within a configurable timeout (e.g., 3 minutes), the system automatically transmits a Rest Mode command.

2. **Unexpected Disconnect / Sleep (Physical = `STANDBY` & Database = `ACTIVE`):**
   * **Trigger:** Accidental power cord kick, accidental manual sleep, or game crash.
   * **Action:** Billing clock automatically pauses to protect customer from unfair charges.
   * **Dashboard Status:** Station card displays a warning: *"Station 2: Console Suspended - Timer Paused"*.
   * **Auto-Resume:** When console wakes up, cashier is prompted to resume the timer with an optional complimentary 2-minute buffer.

---

## 7. Cashier Dashboard UI/UX Specification

The cashier dashboard is designed as a responsive, high-visibility real-time station grid.

### 7.1. Station Card Visual States

* 🟢 **Green (Available / Idle):** 
  * Large Station Number (e.g., "PS5 #04").
  * Status: "Ready".
  * Action: Prominent `[ Start Session ]` button.
* 🔵 **Blue (Active - Prepaid Countdown):**
  * Time Remaining (e.g., `42:15` ticking down).
  * Current Tier (`2 Controllers - $4/hr`).
  * Upfront Payment Status (`PAID` in green pill or `PAY ON EXIT` in yellow pill).
  * Quick Actions: `[ +15m ]` `[ +30m ]` `[ +1h ]` `[ Switch Tier ]` `[ Stop ]`.
* 🟣 **Purple (Active - Postpaid Open Tab):**
  * Elapsed Time (e.g., `01:18:40` ticking up).
  * Running Total Cost (e.g., `$9.20`).
  * Current Tier (`4 Controllers - $7/hr`).
  * Quick Actions: `[ Add Snack/Drink ]` `[ Switch Tier ]` `[ Pause ]` `[ End & Settle ]`.
* 🟡 **Yellow (Expiring Soon):**
  * Triggered when prepaid time $< 5	ext{ minutes}$.
  * Warning pulse on card.
* 🔴 **Red (Rogue / Unauthorized Active):**
  * Flashing red banner: `⚠️ UNAUTHORIZED PLAY DETECTED`.
  * Timer showing unauthorized active duration.
  * Quick Actions: `[ Create Session ]` `[ Force Remote Sleep ]`.

---

## 8. Database Schema & Data Models

Below is the relational data model supporting sessions, dynamic intervals, pricing, and rogue auditing.

```
┌─────────────────┐       ┌────────────────────────┐       ┌──────────────────────┐
│    stations     │       │     pricing_tiers      │       │       sessions       │
├─────────────────┤       ├────────────────────────┤       ├──────────────────────┤
│ id (PK)         │       │ id (PK)                │       │ id (PK)              │
│ name            │       │ name                   │       │ station_id (FK)      │
│ ip_address      │       │ controller_count (1..4)│       │ session_type         │
│ mac_address     │       │ hourly_rate            │       │ status               │
│ device_id       │       │ is_active              │       │ start_time           │
│ pairing_secret  │       └───────────┬────────────┘       │ end_time             │
│ current_state   │                   │                    │ total_amount         │
└────────┬────────┘                   │                    │ payment_status       │
         │                            │                    └──────────┬───────────┘
         │                            │                               │
         │                            │        ┌──────────────────────┼──────────────────────┐
         │                            │        │                      │                      │
         ▼                            ▼        ▼                      ▼                      ▼
┌─────────────────┐       ┌────────────────────────┐       ┌──────────────────────┐┌─────────────────┐
│ station_audits  │       │   session_intervals    │       │     order_items      ││  transactions   │
├─────────────────┤       ├────────────────────────┤       ├──────────────────────┤├─────────────────┤
│ id (PK)         │       │ id (PK)                │       │ id (PK)              ││ id (PK)         │
│ station_id (FK) │       │ session_id (FK)        │       │ session_id (FK)      ││ session_id (FK) │
│ event_type      │       │ pricing_tier_id (FK)   │       │ item_name            ││ shift_id (FK)   │
│ physical_state  │       │ started_at             │       │ unit_price           ││ amount          │
│ expected_state  │       │ ended_at               │       │ quantity             ││ payment_method │
│ resolved_action │       │ subtotal_amount        │       │ subtotal             ││ cashier_id      │
│ created_at      │       └────────────────────────┘       └──────────────────────┘│ timestamp        │
└─────────────────┘                                                               └─────────────────┘
```

### Table Specifications

1. **`stations`**: Stores hardware identifiers, network addressing, and pairing secrets.
2. **`pricing_tiers`**: Defines controller-based and VIP hourly pricing configurations.
3. **`sessions`**: Root transaction record for each customer visit.
4. **`session_intervals`**: Captures sliced billing blocks whenever controller tiers or station rates switch mid-session.
5. **`order_items`**: Tracks retail snacks, drinks, or accessory rentals attached to a session tab.
6. **`transactions`**: Financial records tracking payment amounts, methods (Cash, Card), cashier ID, and shift ID.
7. **`station_audits`**: Complete chronological audit trail of physical wakeups, rogue detections, auto-sleep enforcements, and cashier overrides.

---

## 9. Shift Management, POS & Financial Integrity

* **Shift Open / Close Ledger:** Cashiers declare opening cash float at shift start. All cash collections and card settlements are attributed to the active shift.
* **End-of-Shift Blind Drop:** Cashier enters physical cash counted; system generates discrepancy report (Expected vs. Actual Cash).
* **Audit Trail Protection:** Any session price overrides, manual discounts, or suppressed rogue alerts require cashier justification and are logged to `station_audits` with manager visibility.

---

## 10. Resilience & Edge Case Policy Matrix

| Edge Case / Failure Mode | System Handling Policy |
| :--- | :--- |
| **Server Crash / Reboot** | On startup, server queries active sessions in DB and immediately polls network state. Timers recalculate elapsed time using server clock timestamps ($T_{	ext{now}} - T_{	ext{start}}$) so zero billable time is lost. |
| **Network Switch Power Loss** | Poller marks all stations `NO RESPONSE`. Billing timers freeze or continue based on emergency store policy. Cashier UI flags global network alert. |
| **Station Transfer** | Cashier clicks `[ Transfer Station ]`. System puts Source PS5 to sleep, wakes Destination PS5, and moves the session record and accumulated intervals seamlessly. |
| **Customer Overstay on Prepaid** | When countdown hits 0, server immediately issues network sleep packet. If cashier enabled "Allow Overtime", session transitions automatically into Postpaid mode at the active tier rate. |

---
*Document Version: 1.0.0 — Planning & Logic Specification*
