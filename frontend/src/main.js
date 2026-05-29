import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import 'primeicons/primeicons.css'
import './style.css'
import App from './App.vue'
import router from './router'

const createTimePickerWheelHandler = (unit) => ({ instance, props }) => ({
    onWheel: (event) => {
        if (!props.showTime && !props.timeOnly) return
        if (props.disabled || props.readonly || instance?.isEnabled?.() === false) return

        event.preventDefault()

        const direction = event.deltaY < 0 ? 1 : -1
        const increment = {
            hour: instance.incrementHour,
            minute: instance.incrementMinute,
            second: instance.incrementSecond,
            ampm: instance.toggleAMPM
        }[unit]
        const decrement = {
            hour: instance.decrementHour,
            minute: instance.decrementMinute,
            second: instance.decrementSecond,
            ampm: instance.toggleAMPM
        }[unit]

        const action = direction > 0 ? increment : decrement

        action?.call(instance, event)
        if (unit !== 'ampm') {
            instance.updateModelTime?.()
        }
    }
})

const datePickerPt = {
    hourPicker: createTimePickerWheelHandler('hour'),
    minutePicker: createTimePickerWheelHandler('minute'),
    secondPicker: createTimePickerWheelHandler('second'),
    ampmPicker: createTimePickerWheelHandler('ampm')
}

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(ToastService)
app.use(ConfirmationService)
app.directive('tooltip', Tooltip)
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: '.dark',
        }
    },
    pt: {
        datepicker: datePickerPt,
        calendar: datePickerPt
    }
})

app.mount('#app')
