import {
	CloudOutlined,
	GlobalOutlined,
	LoadingOutlined,
} from '@ant-design/icons'
import ContentCard from '@/components/ContentCard'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
	Button,
	Card,
	Col,
	Empty,
	Form,
	Input,
	notification,
	Row,
	Select,
	Spin,
	Tabs,
	Tag,
} from 'antd'
import { TabsProps } from 'antd/lib'
import { AxiosResponse } from 'axios'
import { atom, useAtomValue, useSetAtom } from 'jotai'
import { useEffect, useRef } from 'react'

import {
	EPowercloudIdentityStatusEnum,
	powercloudIdentityAtom,
} from '../../Atom/powercloud.atom'
import { setTabAtom, TabKeyEnum } from '../../Atom/tab.atom'

import { axios, powerCloudAxios, usePowerCloudAxiosWithApiKey } from '@/api'
import { identityAtom } from '@/pages/AdminApp/Atom/atom'
import {
	allowed_template_options,
	host_positions,
	is_kiwissec,
	kebab,
} from '@/utils'
import { generateRandomPassword } from '@/utils/functions/password'
import { generateRandomWpsiteProConfig } from '@/utils/functions/wordpress'

const { Item } = Form

type TManualSiteSyncParams = {
	site_id: string
	host_position: string
}

interface IPowercloudPackage {
	id: string
	name: string
	price: string
	description: string
	userId: string | null
	cupLimit: string
	cupRequest: string
	memoryLimit: string
	memoryRequest: string
	mysqlSize: string
	wordpressSize: string
	isPublic: boolean
	isActive: boolean
	createdAt: string
	deletedAt: string | null
	updatedAt: string
}

interface IPowercloudTemplate {
	id: string
	primaryDomain: string
	domain?: string
	subDomain?: string
	wildcardDomain: string
}

// 選中的方案 Atom
const selectedPackageIdAtom = atom<string | null>(null)

// Powercloud 內部 tab 的狀態
const powercloudActiveTabAtom = atom<string>('website-package-list')

const PowercloudPakcageList = () => {
	const powerCloudInstance = usePowerCloudAxiosWithApiKey(powerCloudAxios)
	const setSelectedPackageId = useSetAtom(selectedPackageIdAtom)
	const selectedPackageId = useAtomValue(selectedPackageIdAtom)
	const setPowercloudActiveTab = useSetAtom(powercloudActiveTabAtom)

	const { data, isLoading } = useQuery({
		queryKey: ['powercloud-package-list'],
		queryFn: () => powerCloudInstance.get('/website-packages'),
	})

	const websitePackages: IPowercloudPackage[] =
		(data?.data?.data as IPowercloudPackage[]) || []

	const handleSelectPackage = (packageId: string) => {
		setSelectedPackageId(packageId)

		// 切換到開站 tab
		setPowercloudActiveTab('open-site')
	}

	if (isLoading) {
		return (
			<div className="flex justify-center items-center min-h-[300px]">
				<Spin size="large" />
			</div>
		)
	}

	if (websitePackages.length === 0) {
		return (
			<div className="flex justify-center items-center min-h-[300px]">
				<Empty description="暫無方案" />
			</div>
		)
	}

	return (
		<div className="p-4">
			<Row gutter={[16, 16]}>
				{websitePackages.map((pkg) => (
					<Col xs={24} sm={12} lg={8} xl={6} key={pkg.id}>
						<Card
							title={
								<div className="flex justify-between items-center">
									<span className="text-lg font-semibold">{pkg.name}</span>
									<div className="flex gap-2">
										{pkg.isPublic && (
											<Tag color="blue" className="m-0">
												公開
											</Tag>
										)}
										{pkg.isActive ? (
											<Tag color="green" className="m-0">
												啟用
											</Tag>
										) : (
											<Tag color="red" className="m-0">
												停用
											</Tag>
										)}
									</div>
								</div>
							}
							hoverable
							className={`h-full cursor-pointer transition-all ${selectedPackageId === pkg.id
									? 'border-2 border-primary shadow-lg'
									: 'border'
								}`}
							onClick={() => handleSelectPackage(pkg.id)}
						>
							<div className="space-y-3">
								<div className="text-2xl font-bold text-primary">
									NT$ {pkg.price}
								</div>

								{pkg.description && (
									<div className="text-gray-600 text-sm min-h-[40px]">
										{pkg.description}
									</div>
								)}

								<div className="pt-3 space-y-2 border-t">
									<div className="flex justify-between text-sm">
										<span className="text-gray-500">CPU 限制:</span>
										<span className="font-medium">{pkg.cupLimit}</span>
									</div>
									<div className="flex justify-between text-sm">
										<span className="text-gray-500">CPU 請求:</span>
										<span className="font-medium">{pkg.cupRequest}</span>
									</div>
									<div className="flex justify-between text-sm">
										<span className="text-gray-500">記憶體限制:</span>
										<span className="font-medium">{pkg.memoryLimit}</span>
									</div>
									<div className="flex justify-between text-sm">
										<span className="text-gray-500">記憶體請求:</span>
										<span className="font-medium">{pkg.memoryRequest}</span>
									</div>
									<div className="flex justify-between text-sm">
										<span className="text-gray-500">MySQL 大小:</span>
										<span className="font-medium">{pkg.mysqlSize}</span>
									</div>
									<div className="flex justify-between text-sm">
										<span className="text-gray-500">WordPress 大小:</span>
										<span className="font-medium">{pkg.wordpressSize}</span>
									</div>
								</div>

								<Button
									type={selectedPackageId === pkg.id ? 'primary' : 'default'}
									block
									className="mt-4"
									onClick={(e) => {
										e.stopPropagation()
										handleSelectPackage(pkg.id)
									}}
								>
									{selectedPackageId === pkg.id ? '已選擇' : '選擇此方案'}
								</Button>
							</div>
						</Card>
					</Col>
				))}
			</Row>
		</div>
	)
}

type TPowercloudOpenSiteParams = {
	packageId: string
	namespace: string
	wildcardDomain: string
	mysql: {
		auth: {
			rootPassword: string
			password: string
		}
	}
	wordpress: {
		autoInstall: {
			adminUser: string
			adminPassword: string
			adminEmail: string
			siteTitle: string
		}
	}
	ip?: string
	templateUrl?: string
}

const PowercloudOpenSite = () => {
	const containerRef = useRef<HTMLDivElement>(null)
	const [form] = Form.useForm()
	const [api, contextHolder] = notification.useNotification({
		placement: 'bottomRight',
		stack: { threshold: 1 },
		duration: 10,
	})
	const powerCloudInstance = usePowerCloudAxiosWithApiKey(powerCloudAxios)
	const identity = useAtomValue(identityAtom)
	const selectedPackageId = useAtomValue(selectedPackageIdAtom)

	// 取得 package 列表
	const { data: packagesData, isLoading: isLoadingPackages } = useQuery({
		queryKey: ['powercloud-package-list'],
		queryFn: () => powerCloudInstance.get('/website-packages'),
	})

	const websitePackages: IPowercloudPackage[] =
		(packagesData?.data?.data as IPowercloudPackage[]) || []

	// 取得模板列表
	const { data: templatesData, isLoading: isLoadingTemplates } = useQuery({
		queryKey: ['powercloud-template-list'],
		queryFn: () =>
			powerCloudInstance.get('/templates/wordpress?page=1&limit=250'),
	})

	const websiteTemplates: IPowercloudTemplate[] =
		(templatesData?.data?.data as IPowercloudTemplate[]) || []

	const getTemplateDomain = (template: IPowercloudTemplate): string =>
		template.primaryDomain ||
		template.domain ||
		template.subDomain ||
		template.wildcardDomain

	// 當選中的方案改變時，自動設置表單值
	useEffect(() => {
		if (selectedPackageId) {
			form.setFieldsValue({
				packageId: selectedPackageId,
			})
		}
	}, [selectedPackageId, form])

	const { mutate: createWordPress, isPending } = useMutation({
		mutationFn: (params: TPowercloudOpenSiteParams) => {
			return powerCloudInstance.post('/wordpress', params)
		},
		onMutate: () => {
			api.open({
				key: 'powercloud-open-site',
				message: '正在發送開站請求至 Powercloud 伺服器...',
				description: '正在發送請求中...請稍候',
				duration: 0,
				icon: <LoadingOutlined className="text-primary" />,
			})
		},
		onError: (err: any) => {
			console.log('err', err)
			api.error({
				key: 'powercloud-open-site',
				message: 'OOPS! 開站時發生問題',
				description: err?.response?.data?.message || err?.message || '未知錯誤',
			})
		},
		onSuccess: (response: AxiosResponse, variables) => {
			if (response?.status >= 300) {
				return
			}

			// 開站成功，發送郵件通知
			// 調用發送郵件 API
			axios
				.post(`/${kebab}/send-site-credentials-email`, {
					adminEmail: variables.wordpress.autoInstall.adminEmail,
					domain: variables.domain,
					frontUrl: `https://${variables.domain}`,
					adminUrl: `https://${variables.domain}/wp-admin`,
					username: variables.wordpress.autoInstall.adminUser,
					password: variables.wordpress.autoInstall.adminPassword,
					ip: variables.ip || '',
				})
				.then(() => {
					api.success({
						key: 'powercloud-open-site',
						message: '開站成功並已發送郵件',
						description: `站台 ${variables.domain} 已建置完成，帳號密碼已寄送至您的信箱`,
						duration: 10,
					})
				})
				.catch((err) => {
					console.error('發送郵件失敗:', err)
					api.warning({
						key: 'powercloud-open-site',
						message: '開站成功但郵件發送失敗',
						description: '站台已建置完成，但郵件發送失敗，請手動記錄帳號密碼',
						duration: 10,
					})
				})
			form.resetFields()
		},
	})

	const handleGenerateRandomPassword = (prefix: string) => {
		return generateRandomPassword(prefix)
	}

	const handleFinish = () => {
		form
			.validateFields()
			.then((values) => {
				// 生成隨機配置（只調用一次）
				const wpsiteConfig = generateRandomWpsiteProConfig()

				const { adminEmail, templateId, ...data } = values

				// 解析模板 URL
				const selectedTemplate = templateId
					? websiteTemplates.find((tpl) => tpl.id === templateId)
					: undefined
				const templateUrl = selectedTemplate
					? getTemplateDomain(selectedTemplate)
					: undefined

				createWordPress({
					...data,
					namespace: wpsiteConfig.namespace,
					wildcardDomain: wpsiteConfig.domain,
					wordpress: {
						autoInstall: {
							siteTitle: 'WordPress Site',
							adminUser: adminEmail || identity.data?.email || '',
							adminPassword: handleGenerateRandomPassword('wordpress'),
							adminEmail: adminEmail || identity.data?.email || '',
						},
					},
					mysql: {
						auth: {
							rootPassword: handleGenerateRandomPassword('mysql-root'),
							password: handleGenerateRandomPassword('mysql'),
						},
					},
					...(templateUrl ? { templateUrl } : {}),
				})
			})
			.catch((error) => {
				console.log('表單驗證失敗:', error)
			})
	}

	return (
		<div ref={containerRef}>
			<Form
				form={form}
				className="mt-8"
				layout="vertical"
				style={{ maxWidth: 800 }}
			>
				{contextHolder}

				<Form.Item
					label="選擇方案"
					name={['packageId']}
					rules={[{ required: true, message: '請選擇方案' }]}
				>
					<Select
						placeholder="選擇方案"
						loading={isLoadingPackages}
						options={websitePackages.map((pkg) => ({
							label: `${pkg.name} - NT$ ${pkg.price}`,
							value: pkg.id,
						}))}
						disabled={isPending}
						getPopupContainer={() => containerRef.current as HTMLElement}
					/>
				</Form.Item>

				<Form.Item
					label="網站模板"
					name={['templateId']}
					help="選擇一個模板來創建網站"
				>
					<Select
						placeholder="請選擇網站模板"
						loading={isLoadingTemplates}
						options={websiteTemplates.map((tpl) => ({
							label: getTemplateDomain(tpl),
							value: tpl.id,
						}))}
						allowClear
						showSearch
						filterOption={(input, option) =>
							(option?.label as string)
								?.toLowerCase()
								.includes(input.toLowerCase()) ?? false
						}
						disabled={isPending}
						getPopupContainer={() => containerRef.current as HTMLElement}
					/>
				</Form.Item>

				<Form.Item
					label="開站完成後信息寄送Email"
					name={['adminEmail']}
					rules={[
						{ required: true, message: '請輸入開站完成後信息寄送Email的信箱' },
					]}
				>
					<Input
						placeholder="請輸入開站完成後信息寄送Email的信箱"
						disabled={isPending}
					/>
				</Form.Item>

				<div className="flex gap-x-2 mt-8">
					<Button type="primary" loading={isPending} onClick={handleFinish}>
						建立站台
					</Button>
				</div>
			</Form>
		</div>
	)
}

const powercloudItems: TabsProps['items'] = [
	{
		key: 'website-package-list',
		icon: '',
		label: '方案',
		children: <PowercloudPakcageList />,
		forceRender: false,
	},
	{
		key: 'open-site',
		icon: '',
		label: '開站',
		children: <PowercloudOpenSite />,
		forceRender: false,
	},
]

const Powercloud = () => {
	const powercloudIdentity = useAtomValue(powercloudIdentityAtom)
	const setTab = useSetAtom(setTabAtom)
	const activeTab = useAtomValue(powercloudActiveTabAtom)
	const setPowercloudActiveTab = useSetAtom(powercloudActiveTabAtom)

	const handleRedirectToPowercloudAuth = () =>
		setTab(TabKeyEnum.POWERCLOUD_AUTH)

	if (
		powercloudIdentity.status !== EPowercloudIdentityStatusEnum.LOGGED_IN ||
		!powercloudIdentity.apiKey
	) {
		return (
			<Button
				color="primary"
				variant="link"
				onClick={handleRedirectToPowercloudAuth}
			>
				登入新架構
			</Button>
		)
	}
	return (
		<Tabs
			items={powercloudItems}
			tabPosition="left"
			activeKey={activeTab}
			onChange={(key) => setPowercloudActiveTab(key)}
		/>
	)
}

const WPCD = () => {
	const containerRef = useRef<HTMLDivElement>(null)
	const [form] = Form.useForm()
	const [api, contextHolder] = notification.useNotification({
		placement: 'bottomRight',
		stack: { threshold: 1 },
		duration: 10,
	})
	const identity = useAtomValue(identityAtom)

	const queryClient = useQueryClient()

	const { mutate: siteSync, isPending: isPendingSiteSync } = useMutation({
		mutationFn: (params: TManualSiteSyncParams) => {
			return axios.post(`/${kebab}/manual-site-sync`, params)
		},
		onMutate: () => {
			api.open({
				key: 'manual-site-sync',
				message: '正在發送開站請求至站長路可伺服器...',
				description:
					'正在發送請求中...有可能需要等待 🕙 30 秒 ~ 1 分鐘左右的時間，請先不要關閉視窗🙏',
				duration: 0,
				icon: <LoadingOutlined className="text-primary" />,
			})
		},
		onError: (err) => {
			console.log('err', err)
			api.error({
				key: 'manual-site-sync',
				message: 'OOPS! 開站時發生問題',
			})
		},
		onSuccess: (data) => {
			const status = data?.data?.status
			const message = data?.data?.message
			const email = identity?.data?.email || ''

			if (200 === status) {
				api.success({
					key: 'manual-site-sync',
					message: '已經收到您的開站請求',
					description: (
						<>
							站長路可伺服器正在處理您的請求，大約等待 🕙 5 ~ 10
							分鐘左右，開站完成後會將相關資訊寄送到您信箱 {email}`
						</>
					),
					duration: 0,
				})
				queryClient.invalidateQueries({ queryKey: ['apps'] })
			} else {
				api.error({
					key: 'manual-site-sync',
					message: 'OOPS! 開站時發生問題',
					description: message,
				})
			}
		},
	})

	const handleFinish = () => {
		const values: TManualSiteSyncParams = form.getFieldsValue()
		siteSync(values)
	}

	// clear cache

	const { mutate: mutateClearCache, isPending: isPendingClearCache } =
		useMutation({
			mutationFn: () => {
				return axios.post(`/${kebab}/clear-template-sites-cache`)
			},
			onMutate: () => {
				api.open({
					key: 'clear-template-sites-cache',
					message: '正在清除模板站快取...',
					duration: 0,
					icon: <LoadingOutlined className="text-primary" />,
				})
			},
			onError: (err) => {
				console.log('err', err)
				api.error({
					key: 'clear-template-sites-cache',
					message: 'OOPS! 清除模板站快取時發生問題',
				})
			},
			onSuccess: (data) => {
				const status = data?.data?.status
				const message = data?.data?.message

				if (200 === status) {
					api.success({
						key: 'clear-template-sites-cache',
						message: '已經清除模板站快取，3 秒後將重新整理頁面',
					})

					// refresh page

					setTimeout(() => {
						window.location.reload()
					}, 3000)
				} else {
					api.error({
						key: 'clear-template-sites-cache',
						message: 'OOPS! 清除模板站快取時發生問題',
						description: message,
					})
				}
			},
		})

	const handleClearCache = () => {
		mutateClearCache()
	}

	const isPending = isPendingSiteSync || isPendingClearCache

	return (
		<div ref={containerRef}>
			<Form
				form={form}
				className="mt-8"
				layout="vertical"
				labelCol={{ span: 8 }}
				wrapperCol={{ span: 16 }}
				style={{ maxWidth: 600 }}
			>
				{contextHolder}
				<Item
					label="選擇開站模板"
					name={['site_id']}
					rules={[
						{
							required: true,
							message: '請選擇開站模板',
						},
					]}
				>
					<Select
						options={Object.keys(allowed_template_options).map((key) => ({
							label: allowed_template_options?.[key],
							value: key,
						}))}
						allowClear
						disabled={isPending}
						getPopupContainer={() => containerRef.current as HTMLElement}
					/>
				</Item>
				<Item
					label="選擇主機種類"
					name={['host_position']}
					initialValue="jp"
					rules={[
						{
							required: true,
							message: '請選擇主機種類',
						},
					]}
				>
					<Select
						options={
							is_kiwissec
								? [
									...host_positions,
									{
										value: 'kiwissec',
										label: '七維思',
									},
								]
								: host_positions
						}
						allowClear
						disabled={isPending}
						getPopupContainer={() => containerRef.current as HTMLElement}
					/>
				</Item>

				<div className="flex gap-x-2 mt-12">
					<Button type="primary" loading={isPending} onClick={handleFinish}>
						開站
					</Button>

					<Button
						type="default"
						htmlType="button"
						className="ml-2"
						onClick={handleClearCache}
					>
						清除快取
					</Button>
				</div>
			</Form>
		</div>
	)
}

const siteTypeItems: TabsProps['items'] = [
	{
		key: 'powercloud',
		icon: <CloudOutlined />,
		label: '新架構',
		children: <Powercloud />,
		forceRender: false,
	},
	{
		key: 'wpcd',
		icon: <GlobalOutlined />,
		label: '舊架構',
		children: <WPCD />,
		forceRender: false,
	},
]

const index = () => {
	return (
		<ContentCard>
			<Tabs items={siteTypeItems} />
		</ContentCard>
	)
}

export default index
