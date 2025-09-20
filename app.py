import requests
import time
import subprocess
import datetime
import os
import psutil
import platform

# ==== CONFIG ====

FIVEM_API_URL = "http://127.0.0.1:30120/dynamic.json"
FIVEM_PORT = 30120
PING_TARGETS = ["8.8.8.8", "1.1.1.1"]
DISCORD_WEBHOOK = "https://discord.com/api/webhooks/XXXX/XXXX"  # Replace with your webhook
LOG_FILE = r"C:\monitor\fivem_monitor.log"
TCP_LOG_DIR = r"C:\monitor\fivem_tcp_logs"
PLAYER_DROP_THRESHOLD = 0
HIGH_LOSS_THRESHOLD = 50  # percent
INTERVAL = 1  # seconds


# ==== HELPERS ====


def log(msg: str):
    timestamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    line = f"{timestamp} | {msg}"
    print(line)
    with open(LOG_FILE, "a", encoding="utf-8") as f:
        f.write(line + "\n")


def get_fivem_clients() -> int or None:
    try:
        r = requests.get(FIVEM_API_URL, timeout=1)
        r.raise_for_status()
        data = r.json()
        return int(data.get("clients", -1))
    except Exception as e:
        log(f"Failed to get FiveM clients: {e}")
        return None


def get_ping_loss(target: str) -> int:
    param = "-n" if platform.system().lower() == "windows" else "-c"
    try:
        result = subprocess.run(
            ["ping", param, "1", target],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return 0 if result.returncode == 0 else 100
    except Exception:
        return 100


def get_avg_packet_loss() -> float:
    losses = [get_ping_loss(t) for t in PING_TARGETS]
    return sum(losses) / len(losses)


def get_tcp_snapshot() -> str:
    try:
        if platform.system().lower() == "windows":
            cmd = f"netstat -ano | findstr :{FIVEM_PORT}"
            result = subprocess.check_output(cmd, shell=True, text=True)
        else:
            cmd = f"ss -tn state established '( sport = :{FIVEM_PORT} )'"
            result = subprocess.check_output(cmd, shell=True, text=True)
        return result.strip()
    except Exception as e:
        return f"Failed to capture TCP snapshot: {e}"


def save_tcp_snapshot(snapshot: str, event_type: str) -> str:
    if not os.path.exists(TCP_LOG_DIR):
        os.makedirs(TCP_LOG_DIR)
    filename = os.path.join(
        TCP_LOG_DIR, f"{event_type}_{datetime.datetime.now():%Y%m%d_%H%M%S}.log"
    )
    with open(filename, "w", encoding="utf-8") as f:
        f.write(snapshot)
    return filename


def send_discord_alert(message: str, extra_info: str = ""):
    content = f":rotating_light: **ALERT:** {message}"
    if extra_info:
        content += f"\n**TCP Snapshot:**\n```\n{extra_info}\n```"
    payload = {"content": content}
    try:
        r = requests.post(DISCORD_WEBHOOK, json=payload, timeout=5)
        r.raise_for_status()
    except Exception as e:
        log(f"Failed to send Discord alert: {e}")


def get_system_metrics():
    cpu = psutil.cpu_percent(interval=0.1)
    mem = psutil.virtual_memory()
    mem_percent = mem.percent
    disk_queue = 0  # Placeholder; advanced disk queue metrics require platform-specific calls
    return cpu, mem_percent, disk_queue


# ==== MAIN LOOP ====

def main():
    last_player_count = None
    log("FiveM Monitoring Started")

    while True:
        ts = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cpu, mem_percent, disk_queue = get_system_metrics()
        avg_loss = get_avg_packet_loss()
        clients = get_fivem_clients()
        if clients is None:
            clients_display = "no_response"
        else:
            clients_display = str(clients)

        log(
            f"CPU: {cpu:.1f}% | MEM: {mem_percent}% | DiskQueue: {disk_queue} | PING Loss: {avg_loss}% | Clients: {clients_display}"
        )

        if (
            isinstance(clients, int)
            and clients == PLAYER_DROP_THRESHOLD
            and last_player_count is not None
            and last_player_count > PLAYER_DROP_THRESHOLD
        ):
            snapshot = get_tcp_snapshot()
            save_tcp_snapshot(snapshot, "playerdrop")
            send_discord_alert(
                f"All players dropped! (Was {last_player_count} -> Now {clients_display}) at {ts}",
                snapshot,
            )

        if avg_loss >= HIGH_LOSS_THRESHOLD:
            snapshot = get_tcp_snapshot()
            save_tcp_snapshot(snapshot, "highloss")
            send_discord_alert(
                f"High packet loss: {avg_loss}% at {ts}",
                snapshot,
            )

        last_player_count = clients
        time.sleep(INTERVAL)


if __name__ == "__main__":
    main()
