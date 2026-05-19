import antfu from '@antfu/eslint-config'

export default antfu({
  vue: true,
  typescript: true,
  ignores: [
    'public/build',
    'vendor',
    'node_modules',
  ],
})
