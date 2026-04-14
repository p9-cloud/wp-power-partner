import { Typography, Alert, Image } from 'antd'
import preview from '@/assets/images/preview.jpg'
import ContentCard from '@/components/ContentCard'

const SHORT_CODE = '[power_partner_current_user_site_list]'
const { Text } = Typography

const index = () => {
	return (
		<ContentCard>
		<div className="grid grid-cols-2 gap-8">
			<div>
				<Alert
					message="用戶查詢 shortcode"
					description={
						<>
							<p>想要讓你的客戶可以查詢自己的站台嗎?</p>
							<p>
								使用以下 shortcode
								貼在你想要的地方，就可以產生用戶查詢網站的介面
							</p>
							<Text className="bg-yellow-100" copyable>
								{SHORT_CODE}
							</Text>
						</>
					}
					type="info"
					showIcon
				/>
			</div>
			<div>
				<p>預覽: </p>
				<Image
					className="w-full shadow-xl"
					src={preview}
					preview={{
						getContainer: false,
					}}
				/>
			</div>
		</div>
		</ContentCard>
	)
}

export default index
