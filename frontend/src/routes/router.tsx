/* eslint-disable react-refresh/only-export-components -- route config module, not a component file */
import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router-dom'
import { ProtectedRoute } from '@/routes/protected-route'
import { AppLayout } from '@/layouts/app-layout'
import { MigrationRouteGuard } from '@/features/migrations/migration-route-guard'

const LoginPage = lazy(() => import('@/pages/login-page'))
const ForgotPasswordPage = lazy(() => import('@/pages/forgot-password-page'))
const ResetPasswordPage = lazy(() => import('@/pages/reset-password-page'))
const DashboardPage = lazy(() => import('@/pages/dashboard-page'))
const UsersPage = lazy(() => import('@/pages/users-page'))
const RolesPage = lazy(() => import('@/pages/roles-page'))
const CompaniesPage = lazy(() => import('@/pages/companies-page'))
const BusinessFunctionsPage = lazy(() => import('@/pages/business-functions-page'))
const ReferentsPage = lazy(() => import('@/pages/referents-page'))
const ReferentTypesPage = lazy(() => import('@/pages/referent-types-page'))
const OperationalSitesPage = lazy(() => import('@/pages/operational-sites-page'))
const MigrationsPage = lazy(() => import('@/features/migrations/migrations-page'))
const SettingsPage = lazy(() => import('@/pages/settings-page'))
const NotFoundPage = lazy(() => import('@/pages/not-found-page'))

export const router = createBrowserRouter([
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/forgot-password',
    element: <ForgotPasswordPage />,
  },
  {
    path: '/reset-password',
    element: <ResetPasswordPage />,
  },
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <Navigate to="/dashboard" replace /> },
          {
            path: 'dashboard',
            element: <DashboardPage />,
          },
          {
            path: 'users',
            element: <UsersPage />,
          },
          {
            path: 'roles',
            element: <RolesPage />,
          },
          {
            path: 'companies',
            element: <CompaniesPage />,
          },
          {
            path: 'business-functions',
            element: <BusinessFunctionsPage />,
          },
          {
            path: 'referents',
            element: <ReferentsPage />,
          },
          {
            path: 'referent-types',
            element: <ReferentTypesPage />,
          },
          {
            path: 'operational-sites',
            element: <OperationalSitesPage />,
          },
          {
            element: <MigrationRouteGuard />,
            children: [
              {
                path: 'migrations',
                element: <MigrationsPage />,
              },
            ],
          },
          {
            path: 'settings',
            element: <SettingsPage />,
          },
        ],
      },
    ],
  },
  {
    path: '*',
    element: <NotFoundPage />,
  },
])
