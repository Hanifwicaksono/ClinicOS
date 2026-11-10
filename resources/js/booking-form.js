export function bookingSelection(doctors, initial = {}) {
    return {
        doctors,
        doctorId: String(initial.doctor_id ?? ''), serviceId: String(initial.service_id ?? ''),
        scheduleId: String(initial.doctor_schedule_id ?? ''), date: initial.appointment_date ?? '',
        get selectedDoctor() { return this.doctors.find(doctor => String(doctor.id) === String(this.doctorId)); },
        get availableServices() { return this.selectedDoctor?.services ?? []; },
        get availableSchedules() {
            const day = this.date ? new Date(`${this.date}T00:00:00Z`).getUTCDay() : null;
            return (this.selectedDoctor?.schedules ?? []).filter(schedule => day === null || Number(schedule.day) === day);
        },
    };
}

if (typeof window !== 'undefined') window.bookingSelection = bookingSelection;
