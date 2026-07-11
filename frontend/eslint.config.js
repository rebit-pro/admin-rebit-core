import globals from 'globals';
import pluginJs from '@eslint/js';
import tseslint from 'typescript-eslint';
import pluginVue from 'eslint-plugin-vue';
import pluginPrettier from 'eslint-plugin-prettier';
import prettierConfig from '@vue/eslint-config-prettier';

export default [
  {
    name: 'app/files-to-ignore',
    ignores: ['**/dist/**', '**/coverage/**', '**/node_modules/**', '**/reports/**']
  },
  {
    name: 'app/files-to-lint',
    files: ['**/*.{js,mjs,cjs,ts,mts,tsx,vue}'],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node
      },
      parserOptions: {
        parser: '@typescript-eslint/parser'
      }
    },
    plugins: {
      prettier: pluginPrettier
    }
  },
  pluginJs.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/essential'],
  prettierConfig,
  {
    name: 'custom/prettier-rules',
    files: ['**/*.{js,mjs,cjs,ts,mts,tsx,vue}'],
    rules: {
      'prettier/prettier': [
        'error',
        {
          bracketSpacing: true,
          printWidth: 140,
          singleQuote: true,
          trailingComma: 'none',
          tabWidth: 2,
          useTabs: false,
          endOfLine: 'lf'
        }
      ]
    }
  },
  {
    name: 'typescript/no-undef-overlap',
    files: ['**/*.{ts,mts,tsx,vue}'],
    rules: {
      'no-undef': 'off'
    }
  }
];
