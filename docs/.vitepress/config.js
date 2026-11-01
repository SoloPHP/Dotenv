import { defineConfig } from 'vitepress'

const description = 'Strict .env loader for PHP with layered files, server variables first and typed accessors.'

export default defineConfig({
  title: 'Solo Dotenv',
  description,
  base: '/Dotenv/',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/Dotenv/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#10b981' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Solo Dotenv' }],
    ['meta', { property: 'og:description', content: description }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/' },
      { text: 'API', link: '/guide/api' },
      { text: 'Details', link: '/guide/details' },
      { text: 'v2.0.0', link: 'https://github.com/SoloPHP/Dotenv/releases' },
    ],

    sidebar: [
      {
        text: 'Solo Dotenv · 2.0',
        items: [
          { text: 'Getting started', link: '/guide/' },
          { text: 'API reference', link: '/guide/api' },
          { text: 'Behavior in detail', link: '/guide/details' },
          { text: 'Upgrading from 1.x', link: 'https://github.com/SoloPHP/Dotenv/blob/main/UPGRADING.md' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/SoloPHP/Dotenv' },
    ],

    footer: {
      message: 'MIT licensed.',
      copyright: 'Copyright © 2025-2026 SoloPHP',
    },

    editLink: {
      pattern: 'https://github.com/SoloPHP/Dotenv/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    search: {
      provider: 'local',
    },
  },
})
