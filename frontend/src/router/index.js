import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'calendar', component: () => import('../pages/CalendarPage.vue') },
    { path: '/dishes', name: 'dishes', component: () => import('../pages/DishesPage.vue') },
    { path: '/dishes/new', name: 'dish-new', component: () => import('../pages/DishFormPage.vue') },
    {
      path: '/dishes/:id',
      name: 'dish-edit',
      component: () => import('../pages/DishFormPage.vue'),
      props: true,
    },
    {
      path: '/ingredients',
      name: 'ingredients',
      component: () => import('../pages/IngredientsPage.vue'),
    },
    { path: '/weight', name: 'weight', component: () => import('../pages/WeightPage.vue') },
    { path: '/health', name: 'health', component: () => import('../pages/HealthPage.vue') },
  ],
})

export default router
