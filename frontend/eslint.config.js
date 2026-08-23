import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import prettier from 'eslint-config-prettier'

export default [
  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  prettier,
  {
    files: ['src/**/*.{js,vue}'],
    languageOptions: {
      globals: {
        window: 'readonly',
        document: 'readonly',
        fetch: 'readonly',
        console: 'readonly',
        URLSearchParams: 'readonly',
        localStorage: 'readonly',
        confirm: 'readonly',
        alert: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
      },
    },
    rules: {
      // Сторінки називаються за призначенням (CalendarPage тощо), односкладних імен немає,
      // але для компонентів на кшталт DayEditor правило зайве
      'vue/multi-word-component-names': 'off',
    },
  },
  { ignores: ['dist/', 'node_modules/'] },
]
