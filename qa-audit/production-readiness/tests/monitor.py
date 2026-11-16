"""Sample CPU and working sets of the explicitly owned test services on Windows."""
import ctypes
from ctypes import wintypes
import json
import os
import time
import runtime

class FileTime(ctypes.Structure):
    _fields_ = [('low', wintypes.DWORD), ('high', wintypes.DWORD)]

class Memory(ctypes.Structure):
    _fields_ = [('cb', wintypes.DWORD), ('page_faults', wintypes.DWORD),
                ('peak_working_set', ctypes.c_size_t), ('working_set', ctypes.c_size_t),
                ('quota_peak_paged', ctypes.c_size_t), ('quota_paged', ctypes.c_size_t),
                ('quota_peak_nonpaged', ctypes.c_size_t), ('quota_nonpaged', ctypes.c_size_t),
                ('pagefile', ctypes.c_size_t), ('peak_pagefile', ctypes.c_size_t)]

kernel = ctypes.WinDLL('kernel32', use_last_error=True)
kernel.OpenProcess.restype = wintypes.HANDLE
kernel.OpenProcess.argtypes = [wintypes.DWORD, wintypes.BOOL, wintypes.DWORD]
kernel.GetProcessTimes.argtypes = [wintypes.HANDLE, ctypes.POINTER(FileTime), ctypes.POINTER(FileTime), ctypes.POINTER(FileTime), ctypes.POINTER(FileTime)]
kernel.CloseHandle.argtypes = [wintypes.HANDLE]
psapi = ctypes.WinDLL('psapi')
psapi.GetProcessMemoryInfo.argtypes = [wintypes.HANDLE, ctypes.POINTER(Memory), wintypes.DWORD]
handles = {}
for name in ['mysql', 'infra', 'reverb', 'worker', 'http0', 'http1', 'http2', 'http3']:
    pid = int((runtime.ROOT / (name + '.pid')).read_text())
    handle = kernel.OpenProcess(0x410, False, pid)
    if handle:
        handles[name] = handle
assert len(handles) == 8, 'All owned services must be available for measurement'
samples = []
while not (runtime.ROOT / 'monitor.stop').exists():
    processes = {}
    for name, handle in handles.items():
        created, exited, system, user = FileTime(), FileTime(), FileTime(), FileTime()
        memory = Memory(); memory.cb = ctypes.sizeof(memory)
        assert kernel.GetProcessTimes(handle, ctypes.byref(created), ctypes.byref(exited), ctypes.byref(system), ctypes.byref(user))
        assert psapi.GetProcessMemoryInfo(handle, ctypes.byref(memory), memory.cb)
        seconds = (((system.high << 32) + system.low) + ((user.high << 32) + user.low)) / 10000000
        processes[name] = {'cpu_seconds': seconds, 'working_set_bytes': memory.working_set}
    samples.append({'at': time.time(), 'processes': processes})
    (runtime.EVIDENCE / 'resource-samples.json').write_text(json.dumps({'logical_processors': os.cpu_count(), 'samples': samples}))
    time.sleep(1)
for handle in handles.values():
    kernel.CloseHandle(handle)
